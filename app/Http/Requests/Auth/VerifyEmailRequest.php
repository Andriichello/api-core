<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class VerifyEmailRequest.
 *
 * Verifies an email with the parameters of the link, which is emailed to it.
 * They are signed with the application's key, so the link works without
 * signing in, and it is the link's arrival in the inbox, which proves that
 * the person owns the email.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class VerifyEmailRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string', 'max:255'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get the parameters of the link, which verifies the user's email: id of
     * the user, a hash of the email, so the link stops working, when the email
     * changes, the time, when it expires (`auth.verification.expire` minutes
     * from now), and their signature.
     *
     * @param User $user
     *
     * @return array{id: int, hash: string, expires: int, signature: string}
     */
    public static function linkParameters(User $user): array
    {
        $parameters = [
            'id' => (int) $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'expires' => now()->addMinutes((int) config('auth.verification.expire', 60))->getTimestamp(),
        ];

        return $parameters + [
            'signature' => static::sign($parameters, app(Encrypter::class)->getKey()),
        ];
    }

    /**
     * Get the account, whose email the link verifies, when the link is valid:
     * it hasn't expired, it is signed, and the account still has the email.
     *
     * @return User
     * @throws AuthorizationException
     */
    public function account(): User
    {
        $parameters = [
            'id' => $this->integer('id'),
            'hash' => $this->string('hash')->value(),
            'expires' => $this->integer('expires'),
        ];

        $account = $parameters['expires'] >= now()->getTimestamp() && $this->isSigned($parameters)
            ? User::query()->find($parameters['id']) : null;

        if ($account === null || !hash_equals(sha1($account->getEmailForVerification()), $parameters['hash'])) {
            throw new AuthorizationException('The verification link is invalid or has expired.');
        }

        return $account;
    }

    /**
     * Determine if the request's signature is the one of the parameters, made
     * with the application's key or one of its previous keys.
     *
     * @param array{id: int, hash: string, expires: int} $parameters
     *
     * @return bool
     */
    protected function isSigned(array $parameters): bool
    {
        $signature = $this->string('signature')->value();

        foreach (app(Encrypter::class)->getAllKeys() as $key) {
            if (hash_equals(static::sign($parameters, $key), $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sign the parameters with the key. The purpose is signed along with
     * them, so the signature is never valid for anything else.
     *
     * @param array{id: int, hash: string, expires: int} $parameters
     * @param string $key
     *
     * @return string
     */
    protected static function sign(array $parameters, string $key): string
    {
        return hash_hmac('sha256', 'email-verification|' . implode('|', $parameters), $key);
    }
}
