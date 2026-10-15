<?php

namespace Core\Models\Traits;

use Core\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;

/**
 * Trait SharesPasswordAcrossAccounts.
 *
 * Keeps one password for all accounts of a person. A password, which is set
 * on any of them, is set on the main account and every account linked to it,
 * deleted ones included, and an account takes the password of the main
 * account, when it is linked to one. Passwords are checked against the main
 * account (`User::getAuthPassword()`), while the copies make sure an account,
 * which becomes the main one or is unlinked, keeps the person's password.
 * The time, when the person set it (`password_updated_at`), goes along with it.
 *
 * @mixin User
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait SharesPasswordAcrossAccounts
{
    /**
     * Attributes, which make up the person's password.
     *
     * @var array<int, string>
     */
    protected static array $passwordAttributes = ['password', 'password_updated_at'];

    /**
     * Boot the trait, registering its model event listeners. They run after
     * the account is saved, so it is already linked by LinksAccountsByEmail.
     *
     * @return void
     */
    public static function bootSharesPasswordAcrossAccounts(): void
    {
        static::created(fn (User $user) => $user->takePasswordOfMainAccount());

        static::updated(function (User $user) {
            if ($user->wasChanged('main_account_id')) {
                $user->takePasswordOfMainAccount();

                return;
            }

            if ($user->wasChanged(static::$passwordAttributes)) {
                $user->sharePasswordWithAllAccounts();
            }
        });
    }

    /**
     * Take the password of the main account, to which the account is linked.
     *
     * @return void
     */
    protected function takePasswordOfMainAccount(): void
    {
        if ($this->isMainAccount()) {
            return;
        }

        $main = static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($this->main_account_id)
            ->toBase()
            ->first(static::$passwordAttributes);

        if ($main === null) {
            return;
        }

        $password = Arr::only((array) $main, static::$passwordAttributes);

        if ($password === Arr::only($this->getAttributes(), static::$passwordAttributes)) {
            return;
        }

        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($this->getKey())
            ->update($password);

        $this->attributes = array_merge($this->attributes, $password);
        $this->syncOriginalAttributes(static::$passwordAttributes);
    }

    /**
     * Set the account's password on all accounts of the person. It is set on
     * this one too, because a stale model doesn't write `password_updated_at`,
     * which it sees as null already, while another account has set it since.
     *
     * @return void
     */
    protected function sharePasswordWithAllAccounts(): void
    {
        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->ofPerson($this)
            ->update(Arr::only($this->getAttributes(), static::$passwordAttributes));
    }
}
