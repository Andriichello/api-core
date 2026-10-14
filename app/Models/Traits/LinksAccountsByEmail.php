<?php

namespace Core\Models\Traits;

use Core\Models\User;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Trait LinksAccountsByEmail.
 *
 * Links accounts of the same person (in different organizations) to their
 * main account by a verified email, so the main account can switch to them.
 * An email must be verified on both accounts, which proves they belong to
 * the same person, so `email_verified_at` should only be set when the person
 * has confirmed the address.
 *
 * An account is linked when it is created or its email is verified. When its
 * email changes, it is unlinked, and it is linked again by the new email only
 * once that one is verified (`email_verified_at` is set again), because the
 * old verification doesn't prove the new address. Accounts linked to a main
 * account, which changes its email or is deleted permanently, are handed over
 * to the oldest of them.
 *
 * @mixin User
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait LinksAccountsByEmail
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootLinksAccountsByEmail(): void
    {
        static::saving(fn (User $user) => $user->linkAccountByEmail());
        static::forceDeleting(fn (User $user) => $user->handOverRelatedAccounts());
    }

    /**
     * Link the account to the main account of the person, by its email.
     * A main account, which is given explicitly, is kept as it is.
     *
     * @return void
     */
    protected function linkAccountByEmail(): void
    {
        $isGivenExplicitly = $this->isDirty('main_account_id')
            && ($this->exists || $this->main_account_id !== null);

        if ($isGivenExplicitly) {
            return;
        }

        if ($this->exists && $this->isDirty('email')) {
            $this->main_account_id = null;
            $this->handOverRelatedAccounts();
        }

        $isToBeLinked = !$this->exists || $this->isDirty('email_verified_at');

        if (!$isToBeLinked || !$this->isMainAccount() || !$this->hasVerifiedEmail()) {
            return;
        }

        // Linking a main account with accounts of its own would make a chain
        $hasRelatedAccounts = $this->exists && $this->relatedAccounts()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->exists();

        if ($hasRelatedAccounts) {
            return;
        }

        $this->main_account_id = $this->resolveMainAccount()?->mainAccountId();
    }

    /**
     * Get the account, to whose main account this one should be linked:
     * the oldest other account with the same verified email, if any.
     *
     * @return User|null
     */
    protected function resolveMainAccount(): ?User
    {
        $query = static::query()
            ->where('email', $this->email);

        $query->whereNotNull('email_verified_at');

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->oldest('id')->first();
    }

    /**
     * Hand over the accounts linked to this one to the oldest of them,
     * which becomes their main account.
     *
     * @return void
     */
    protected function handOverRelatedAccounts(): void
    {
        $newMainId = $this->relatedAccounts()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->oldest('id')
            ->value('id');

        if ($newMainId === null) {
            return;
        }

        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('main_account_id', $this->getKey())
            ->update(['main_account_id' => $newMainId]);

        static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($newMainId)
            ->update(['main_account_id' => null]);
    }
}
