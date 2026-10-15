<?php

namespace Core\Models\Traits;

use Core\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Trait SharesPasswordAcrossAccounts.
 *
 * Keeps one password for all accounts of a person. A password, which is set
 * on any of them, is set on the main account and every account linked to it,
 * deleted ones included, and an account takes the password of the main
 * account, when it is linked to one. Passwords are checked against the main
 * account (`User::getAuthPassword()`), while the copies make sure an account,
 * which becomes the main one or is unlinked, keeps the person's password.
 *
 * @mixin User
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait SharesPasswordAcrossAccounts
{
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

            if ($user->wasChanged('password')) {
                $user->sharePasswordWithOtherAccounts();
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

        $password = static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($this->main_account_id)
            ->value('password');

        if ($password === null || $password === $this->password) {
            return;
        }

        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($this->getKey())
            ->update(['password' => $password]);

        $this->attributes['password'] = $password;
        $this->syncOriginalAttribute('password');
    }

    /**
     * Set the account's password on the other accounts of the person.
     *
     * @return void
     */
    protected function sharePasswordWithOtherAccounts(): void
    {
        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->ofPerson($this)
            ->whereKeyNot($this->getKey())
            ->update(['password' => $this->password]);
    }
}
