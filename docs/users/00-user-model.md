# The User model

`Core\Models\User` is the account a person signs in to. It belongs to one organization (or to none), has roles, and
may be linked to the person's accounts in other organizations. Set `AUTH_MODEL=Core\Models\User`, and use this
model, rather than one of your own, wherever the application refers to users.

## Columns

| Column                | Type                          | Description                                                     |
|-----------------------|-------------------------------|-----------------------------------------------------------------|
| `id`                  | bigint                        | The key.                                                        |
| `organization_id`     | bigint, nullable              | The account's organization; `null` for internal users. The account is deleted along with the organization's row. |
| `main_account_id`     | bigint, nullable, hidden      | The person's main account, which this one is linked to.         |
| `name`                | string                        | The display name.                                               |
| `email`               | string                        | Stored in lowercase. Unique per organization (see below).       |
| `password`            | string, hidden                | Hashed by the `hashed` cast.                                    |
| `password_updated_at` | timestamp, nullable           | When the person set the password; `null` when someone else did. |
| `timezone`            | string(64), default `UTC`     |                                                                 |
| `locale`              | string(5), default `en`       | Used by the `locale` middleware.                                |
| `metadata`            | JSON, nullable                | Free-form data of the application, cast to an array.            |
| `email_verified_at`   | timestamp, nullable           | `null` until the email is verified, or the invitation accepted. |
| `suspended_at`        | timestamp, nullable           | Set while the account is suspended.                             |
| `last_login_at`       | timestamp, nullable           | Set by `POST /api/auth/login`.                                  |
| `created_at`, `updated_at`, `deleted_at` | timestamps | The model is soft-deletable.                                  |

Only `name`, `email`, `password`, `timezone` and `locale` are mass-assignable. Set the other columns with
`forceFill()`. `password` and `main_account_id` are hidden from the model's array and JSON.

## Behavior on saving

- The email is lowercased when it is set, so it is matched regardless of case on every database.
- A changed email becomes unverified (`email_verified_at` is cleared), unless the same save verifies it.
- A password set without `password_updated_at` in the same save clears `password_updated_at`, so a password set by
  an admin or a seeder must be replaced by the person (see
  [Required password update](../auth/10-required-password-update.md)).
- The account is linked to the person's main account by its verified email, and shares the person's password (see
  [Linked accounts](../auth/06-linked-accounts.md) and [Passwords](../auth/07-passwords.md)).

## Relations

| Relation          | Type        | Description                                                              |
|-------------------|-------------|--------------------------------------------------------------------------|
| `organization`    | `BelongsTo` | The account's `Organization`.                                            |
| `mainAccount`     | `BelongsTo` | The main account, which this one is linked to.                           |
| `relatedAccounts` | `HasMany`   | The accounts linked to this main account.                                |
| `tokens`          | `MorphMany` | The account's access and refresh tokens (`Core\Models\AccessToken`).     |
| `roles`, `permissions` | spatie | The account's roles and direct permissions.                          |
| `notifications`   | `MorphMany` | Database notifications, newest first, then by id.                       |

## Accounts of a person

`isMainAccount()` tells whether the account is linked to no other one, `mainAccountId()` returns the id of the
person's main account (its own id for a main account), and `isSamePerson(User $user)` whether two accounts belong to
the same person. How accounts get linked is described in [Linked accounts](../auth/06-linked-accounts.md).

## Suspension

There is no endpoint to suspend an account; the application sets `suspended_at`:

```php
$user->forceFill(['suspended_at' => now()])->save();
```

`isSuspended()` tells whether it is set. `canSignIn()` is false while the account is suspended, and while its
organization is suspended, deactivated or deleted; it is true for active accounts, with or without an organization.
An account that can't sign in:

- is refused at login with `422` and `This account is suspended.` on `email`;
- has its access tokens refused with `401`, until it can sign in again (unless they expire first);
- has its sessions signed out, for good;
- can't be switched to (`This account is suspended.` on `user_id`).

## Unique emails

An email is unique among the accounts of an organization, deleted ones included, and among the accounts without an
organization. The same email may be used in any number of organizations, each time by another account of the person.

Two unique indexes enforce it: `unique(organization_id, email)` and `users_organization_key_email_unique` over
`(coalesce(organization_id, 0), email)`. The second one is needed because a unique index treats `NULL`s as distinct,
so the first one lets two accounts without an organization share an email. The first one stays, since MySQL needs it
for the foreign key of `organization_id`. The expression index needs MySQL 8.0.13 or newer, PostgreSQL or SQLite;
MariaDB doesn't support it. Inviting the email of a deleted account restores that account, rather than creating a new
one (see [Re-inviting deleted users](02-re-inviting-deleted-users.md)).

## UserQuery

`User::query()` returns a `Core\Queries\UserQuery`:

| Method                     | Description                                                                         |
|----------------------------|-------------------------------------------------------------------------------------|
| `index($user)`             | The users the user may list (see [Acting organization](../organizations/04-acting-organization.md)). |
| `ofPerson(User $user)`     | The accounts of the user's person: the main account and the accounts linked to it.  |
| `ofPeople(User ...$users)` | The accounts of the people of the given accounts.                                   |

```php
$accounts = User::query()->ofPerson($user)->get();
```

`index()` gives nothing without a user, all users to a super admin acting in all organizations, and otherwise the
users of the acting organization along with the person's own accounts in any organization.

## Other methods

- `actingOrgId()` and `actsInAllOrganizations()`: see [Acting organization](../organizations/04-acting-organization.md).
- `getAuthPassword()`, `passwordUpdatedAt()` and `isPasswordUpdateRequired()`: see [Passwords](../auth/07-passwords.md).
- `isSignedInWithSession()` and `currentImpersonator()`: see [Sessions](../auth/03-sessions.md) and
  [Switching accounts](../auth/05-switching-accounts.md).
- The role checks of `ChecksRolesAndPermissions`: see [Ranking roles](../roles/03-ranking-roles.md).

In tests, `User::factory()` makes a verified account with the password `password`, and has the `unverified()` and
`requiringPasswordUpdate()` states (see [Factories and seeders](../database/01-factories-and-seeders.md)).

## Related

- [Managing users](01-managing-users.md)
- [Re-inviting deleted users](02-re-inviting-deleted-users.md)
- [Organizations](../organizations/00-overview.md)
- [Roles](../roles/00-overview.md)
- [Linked accounts](../auth/06-linked-accounts.md)
- [Passwords](../auth/07-passwords.md)
- [Login](../auth/01-login.md)
- [Migrations](../database/00-migrations.md)
