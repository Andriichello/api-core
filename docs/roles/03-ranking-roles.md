# Ranking roles

Roles rank in the order of `api-core.roles`, below the super admin, and the ranks decide which roles a user may give
and which users they may manage. This document describes them, along with the methods that check a user's roles and
permissions.

## Ranks

A role's rank is its position in `RoleRepository::names()`: `0` for `super-admin`, then `1`, `2`, ... in the order of
the config. A lower number is a higher role. With the roles `owner`, `accountant` and `viewer`:

| Role          | Rank |
|---------------|------|
| `super-admin` | 0    |
| `owner`       | 1    |
| `accountant`  | 2    |
| `viewer`      | 3    |

A role that isn't defined in the config (one created in the database by other means) has no rank.

## Comparing users and roles

These methods come from `Core\Models\Traits\ChecksRolesAndPermissions` on `User`:

| Method                            | Returns                                                                      |
|-----------------------------------|------------------------------------------------------------------------------|
| `rankOfHighestRole(): ?int`       | The rank of the user's highest defined role, or `null` when they have none.  |
| `isHigherThanRole($role): bool`   | Whether the user's highest role ranks above the role (`UserRole`, `Role` or name). |
| `isHigherThan(User $user): bool`  | Whether the user ranks above every role of the other user.                   |
| `availableRoles(): array`         | The user's own roles and the lower ones, from the highest to the lowest.     |

```php
$accountant->isHigherThanRole('viewer');      // true
$accountant->isHigherThanRole('accountant');  // false: the same rank isn't higher
$owner->isHigherThan($accountant);            // true
$accountant->availableRoles();                // ['accountant', 'viewer']
$superAdmin->availableRoles();                // ['super-admin', 'owner', 'accountant', 'viewer']
```

The rules, including the edge cases:

- A user with several roles ranks by the highest of them.
- A user without a defined role is higher than no role, and has no available roles.
- A user with any role, even one that isn't defined, is higher than a user without roles; a user without roles is
  higher than nobody.
- Only a super admin is higher than a role that isn't defined, and nobody is higher than a super admin.
- Roles that aren't defined are never available, so they can't be given through the API.

## Where ranks are used

- **Roles a manager may give.** `UserController` accepts only roles from the manager's `availableRoles()`
  (`WithAssignableRoles`), and keeps the roles of a user that the manager may not give (see
  [Managing users](../users/01-managing-users.md#roles-a-manager-may-give)).
- **Users a manager may manage.** `UserPolicy` refuses to update, delete (also permanently) or restore a user who
  ranks above the manager (see [User policy](../http/policies/03-user-policy.md)).
- **Re-inviting.** A deleted user who ranks above the manager can't be invited again by them (see
  [Re-inviting deleted users](../users/02-re-inviting-deleted-users.md)).

An admin may therefore make another user an admin, but not a super admin, and can't change a super admin.

## Checking roles and permissions

| Method                              | Returns                                                                   |
|-------------------------------------|---------------------------------------------------------------------------|
| `isOfRole(UserRole\|string $role)`  | Whether the user has the role.                                            |
| `isSuperAdmin()`                    | Whether the user has `super-admin`.                                       |
| `isAdmin()`, `isUser()`, `isGuest()`| Whether the user has the default role `admin`, `user` or `guest`.         |
| `hasRightsOfAdmin()`                | Whether the user has `super-admin` or `admin`.                            |
| `canOn(string $ability, string $resource)` | Whether the user has the `{ability} {morph}` permission of a model class (false for a super admin, who has no permissions). |
| `canImpersonateUser(User $user)`    | Whether the user may switch to the account: the user is a super admin, or the account's main account. |
| `canBeImpersonatedByUser(User $user)` | The same, seen from the other account.                                  |

`isAdmin()`, `isUser()`, `isGuest()` and `hasRightsOfAdmin()` check the default role names, so an application with its
own roles uses `isOfRole()` instead:

```php
if ($user->isOfRole('owner')) {
    // ...
}
```

spatie's methods (`hasRole()`, `hasAnyRole()`, `can()`, `getRoleNames()`) work as well. Switching is described in
[Switching accounts](../auth/05-switching-accounts.md).

## Related

- [Roles](00-overview.md)
- [Configuring roles](01-configuring-roles.md)
- [Syncing roles](02-syncing-roles.md)
- [User policy](../http/policies/03-user-policy.md)
- [Request traits](../http/requests/03-request-traits.md)
- [Managing users](../users/01-managing-users.md)
- [Switching accounts](../auth/05-switching-accounts.md)
