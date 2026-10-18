<?php

namespace Core\Http\Requests\Traits;

use Core\Http\Requests\BaseRequest;
use Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * Trait WithAccountLink.
 *
 * For requests, which come from a link, which was emailed to an account, such
 * as one, which verifies its email. The link's parameters are id of the
 * account, a hash of what the link is bound to (its email, at least), so the
 * link stops working, when that changes, the time, when it expires, and their
 * signature. They are signed, along with the link's purpose, with the
 * application's key, so the link works without signing in, and it is its
 * arrival in the inbox, which proves that the person owns the email.
 *
 * @mixin BaseRequest
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Requests\Traits
 */
trait WithAccountLink
{
    /**
     * Get the purpose of the link, which is signed along with its parameters,
     * so the signature is never valid for anything else.
     *
     * @return string
     */
    abstract protected static function linkPurpose(): string;

    /**
     * Get the number of minutes, for which a link works.
     *
     * @return int
     */
    abstract protected static function linkLifetime(): int;

    /**
     * Get the hash of what the link of the account is bound to.
     *
     * @param User $account
     *
     * @return string
     */
    abstract protected static function linkHashOf(User $account): string;

    /**
     * Get the message of a link, which is invalid or has expired.
     *
     * @return string
     */
    abstract protected function invalidLinkMessage(): string;

    /**
     * Get the validation rules of the link's parameters.
     *
     * @return array<string, array<int, string>>
     */
    protected function linkRules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string', 'max:255'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get the parameters of the account's link, which expires in the link's
     * lifetime from now.
     *
     * @param User $account
     *
     * @return array{id: int, hash: string, expires: int, signature: string}
     */
    public static function linkParameters(User $account): array
    {
        $parameters = [
            'id' => (int) $account->getKey(),
            'hash' => static::linkHashOf($account),
            'expires' => now()->addMinutes(static::linkLifetime())->getTimestamp(),
        ];

        return $parameters + [
            'signature' => static::sign($parameters, app(Encrypter::class)->getKey()),
        ];
    }

    /**
     * Get the account of the link, when the link is valid: it hasn't expired,
     * it is signed, and what it's bound to hasn't changed.
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

        if ($account === null || !hash_equals(static::linkHashOf($account), $parameters['hash'])) {
            throw new AuthorizationException($this->invalidLinkMessage());
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
     * Sign the parameters, along with the link's purpose, with the key.
     *
     * @param array{id: int, hash: string, expires: int} $parameters
     * @param string $key
     *
     * @return string
     */
    protected static function sign(array $parameters, string $key): string
    {
        return hash_hmac('sha256', static::linkPurpose() . '|' . implode('|', $parameters), $key);
    }
}
