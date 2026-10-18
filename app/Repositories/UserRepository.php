<?php

namespace Core\Repositories;

use Core\Enums\UserRole;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Notifications\InvitationNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Class UserRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class UserRepository extends CrudRepository
{
    /**
     * Repository's model class.
     *
     * @var BaseModel|string
     */
    protected BaseModel|string $model = User::class;

    /**
     * UserRepository constructor.
     *
     * @param AccessTokenRepository $tokens
     */
    public function __construct(
        protected AccessTokenRepository $tokens = new AccessTokenRepository
    ) {}

    /**
     * Assign roles to a given user.
     *
     * @param User $user
     * @param UserRole|string ...$roles
     *
     * @return void
     */
    public function assignRole(User $user, UserRole|string ...$roles): void
    {
        $roles = array_map(
            function (UserRole|string $role) {
                return $role instanceof UserRole
                    ? $role->value : $role;
            },
            $roles
        );

        $roles = array_filter(
            $roles,
            function (string $role) use ($user) {
                return !$user->hasRole($role);
            }
        );

        $user->assignRole($roles);
    }

    /**
     * Invite a person to a new account with the given attributes and roles in
     * the organization, and email them the invitation. Until they accept it
     * (see AcceptInvitationRequest), the account's email is unverified and its
     * password is a random one, which nobody knows, so it can't sign in. No
     * password can be given: the person chooses it, so nobody else knows it.
     *
     * @param array<string, mixed> $attributes
     * @param int|null $organizationId
     * @param array<int, UserRole|string> $roles
     * @param User|null $inviter
     *
     * @return User
     */
    public function invite(array $attributes, ?int $organizationId, array $roles = [], ?User $inviter = null): User
    {
        $user = DB::transaction(function () use ($attributes, $organizationId, $roles) {
            $user = new User(Arr::except($attributes, ['password']));

            $user->forceFill([
                'organization_id' => $organizationId,
                'password' => Str::password(64),
                'email_verified_at' => null,
            ])->save();

            $this->assignRole($user, ...$roles);

            return $user;
        });

        $this->sendInvitation($user, $inviter);

        return $user;
    }

    /**
     * Invite a person again to their deleted account, restoring it, so its
     * history stays attached to it. As with a new invitation, the account
     * gets the given attributes and roles, its email is unverified and its
     * password a random one, until they accept the invitation. It leaves the
     * person's other accounts first (see User::unlinkFromPerson()), so their
     * password stays, and it is linked to them again, when the invitation is
     * accepted and the email so verified. Its tokens are deleted.
     *
     * @param User $user
     * @param array<string, mixed> $attributes
     * @param array<int, UserRole|string> $roles
     * @param User|null $inviter
     *
     * @return User
     */
    public function reinvite(User $user, array $attributes, array $roles = [], ?User $inviter = null): User
    {
        DB::transaction(function () use ($user, $attributes, $roles) {
            $user->unlinkFromPerson();

            $user->fill(Arr::except($attributes, ['email', 'password']));
            $user->forceFill([
                'password' => Str::password(64),
                'email_verified_at' => null,
            ]);

            $user->restore();
            $user->syncRoles($roles);

            $this->tokens->deleteOfAccounts($user);
        });

        $this->sendInvitation($user, $inviter);

        return $user;
    }

    /**
     * Find the deleted account with the email in the organization (or among
     * the accounts without one).
     *
     * @param string $email
     * @param int|null $organizationId
     *
     * @return User|null
     */
    public function findDeleted(string $email, ?int $organizationId): ?User
    {
        /** @var User|null $user */
        $user = User::query()
            ->whereTrashed()
            ->where('email', $email)
            ->where('organization_id', $organizationId)
            ->first();

        return $user;
    }

    /**
     * Email the person the invitation to the account, which was made for them.
     *
     * @param User $user
     * @param User|null $inviter
     *
     * @return void
     */
    public function sendInvitation(User $user, ?User $inviter = null): void
    {
        $user->notify(new InvitationNotification($inviter));
    }

    /**
     * Find the accounts, into which the email and password sign in. The password
     * is checked against the main account, as all accounts of a person share it.
     * With the slug of an organization, it is the account in that organization only.
     *
     * @param string $email
     * @param string $password
     * @param string|null $organization
     *
     * @return Collection<int, User>
     */
    public function findByCredentials(string $email, string $password, ?string $organization = null): Collection
    {
        $query = User::query()
            ->where('email', Str::lower($email));

        if ($organization !== null) {
            $query->whereIn(
                'organization_id',
                Organization::query()->select('id')->where('slug', $organization)
            );
        }

        return $query->get()
            ->filter(fn (User $user) => Hash::check($password, $user->getAuthPassword()))
            ->values();
    }

    /**
     * Set the password, which the person chose, on all of their accounts (see
     * SharesPasswordAcrossAccounts), as updated by them (`password_updated_at`).
     *
     * @param User $user
     * @param string $password
     *
     * @return void
     */
    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password, 'password_updated_at' => now()])->save();
    }

    /**
     * Set the password of every person, who has an account with the email, as
     * whoever controls the email may reset the password of any of them. It is
     * set with one query on all accounts of those people, deleted ones included,
     * as SharesPasswordAcrossAccounts would set it, so no model events are fired,
     * and it counts as updated by the person (`password_updated_at`).
     *
     * @param string $email
     * @param string $password
     *
     * @return Collection<int, User> The accounts with the email, one for each
     *                               person, whose password is set.
     */
    public function resetPassword(string $email, string $password): Collection
    {
        $accounts = User::query()
            ->where('email', Str::lower($email))
            ->get()
            ->unique(fn (User $user) => $user->mainAccountId())
            ->values();

        User::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->ofPeople(...$accounts)
            ->update(['password' => Hash::make($password), 'password_updated_at' => now()]);

        return $accounts;
    }
}
