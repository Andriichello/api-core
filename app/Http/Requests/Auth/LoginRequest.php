<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Core\Repositories\UserRepository;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Class LoginRequest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class LoginRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'organization' => ['nullable', 'string', 'max:255'],
            // A session login, which outlives its session in a cookie; tokens have their own lifetimes
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the account, into which the credentials sign in. The organization
     * (its slug) is only needed when the email and password match accounts
     * of several people. When they match accounts of one person, it is
     * their main account, from which they can switch to the others.
     *
     * @return User
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $email = $this->string('email')->value();

        $accounts = app(UserRepository::class)->findByCredentials(
            $email,
            $this->string('password')->value(),
            $this->input('organization')
        );

        // The email alone stands for the credentials of a failed login, so the password isn't passed on
        if ($accounts->isEmpty()) {
            event(new Failed('sanctum', null, ['email' => $email]));
        }

        $user = $this->choose($accounts);

        if (!$user->canSignIn()) {
            event(new Failed('sanctum', $user, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => 'This account is suspended.',
            ]);
        }

        return $user;
    }

    /**
     * Choose the account to sign into from the ones, which match the credentials.
     *
     * @param Collection<int, User> $accounts
     *
     * @return User
     * @throws ValidationException
     */
    protected function choose(Collection $accounts): User
    {
        if ($accounts->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $main = $accounts->first(
            fn (User $account) => $account->isMainAccount()
                && $accounts->every(fn (User $other) => $other->isSamePerson($account))
        );

        if ($accounts->count() > 1 && $main === null) {
            throw ValidationException::withMessages([
                'organization' => 'The email is used in several organizations, so choose one of them.',
            ]);
        }

        return $main ?? $accounts->first();
    }
}
