<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Core\Http\Requests\Traits\WithAccountLink;
use Core\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Class AcceptInvitationRequest.
 *
 * Accepts an invitation to an account with the parameters of the link, which
 * is emailed to it (see WithAccountLink), and the password, which the person
 * chooses. The link is bound to the email and to the account's password, so
 * it works once: accepting sets the password. It works for
 * `api-core.auth.invitation_lifetime` minutes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class AcceptInvitationRequest extends BaseRequest
{
    use WithAccountLink;

    /**
     * Get the validation rules that apply to the request. The password follows
     * the application's `Password::defaults()`.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            ...$this->linkRules(),
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Get the purpose of the link.
     *
     * @return string
     */
    protected static function linkPurpose(): string
    {
        return 'invitation';
    }

    /**
     * Get the number of minutes, for which a link works.
     *
     * @return int
     */
    protected static function linkLifetime(): int
    {
        return (int) config('api-core.auth.invitation_lifetime');
    }

    /**
     * Get the hash of the account's email and password, so the link stops
     * working, when either changes, as accepting changes the password.
     *
     * @param User $account
     *
     * @return string
     */
    protected static function linkHashOf(User $account): string
    {
        return sha1($account->getEmailForVerification() . '|' . $account->getAttribute('password'));
    }

    /**
     * Get the message of a link, which is invalid or has expired.
     *
     * @return string
     */
    protected function invalidLinkMessage(): string
    {
        return 'The invitation link is invalid, has expired or has been used.';
    }
}
