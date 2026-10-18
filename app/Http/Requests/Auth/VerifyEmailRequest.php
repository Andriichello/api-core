<?php

namespace Core\Http\Requests\Auth;

use Core\Http\Requests\BaseRequest;
use Core\Http\Requests\Traits\WithAccountLink;
use Core\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class VerifyEmailRequest.
 *
 * Verifies an email with the parameters of the link, which is emailed to it
 * (see WithAccountLink). The link is bound to the email, and works for
 * `auth.verification.expire` minutes.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Auth
 */
class VerifyEmailRequest extends BaseRequest
{
    use WithAccountLink;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return $this->linkRules();
    }

    /**
     * Get the purpose of the link.
     *
     * @return string
     */
    protected static function linkPurpose(): string
    {
        return 'email-verification';
    }

    /**
     * Get the number of minutes, for which a link works.
     *
     * @return int
     */
    protected static function linkLifetime(): int
    {
        return (int) config('auth.verification.expire', 60);
    }

    /**
     * Get the hash of the account's email, so the link stops working, when the
     * email changes.
     *
     * @param User $account
     *
     * @return string
     */
    protected static function linkHashOf(User $account): string
    {
        return sha1($account->getEmailForVerification());
    }

    /**
     * Get the message of a link, which is invalid or has expired.
     *
     * @return string
     */
    protected function invalidLinkMessage(): string
    {
        return 'The verification link is invalid or has expired.';
    }
}
