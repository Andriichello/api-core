# Organizations

An organization is a tenant of the application: its users and its records are kept apart from those of other
organizations. This document explains how api-core models them, why every account belongs to one organization only,
and what deleting, suspending or deactivating an organization does.

## One organization per account

A user (`Core\Models\User`) has a single nullable `organization_id`. There is no membership pivot, so a person who
works in two organizations has two accounts, one in each, with the same email. The accounts are linked to the
person's main account (`users.main_account_id`) once their email is verified on both, which is what makes them one
person:

- all of the person's accounts share one password;
- `POST /api/auth/login` signs the person into their main account and asks for the organization only when the
  credentials match accounts of different people;
- the main account switches to the linked ones without signing in again.

Linking, logging in and switching are described in [Linked accounts](../auth/06-linked-accounts.md),
[Login](../auth/01-login.md) and [Switching accounts](../auth/05-switching-accounts.md).

Keeping the organization on the account, rather than in a pivot, keeps everything that belongs to an account
per organization without extra machinery:

- Roles are attached to the account (spatie/laravel-permission without teams), so a person can be an admin in one
  organization and a guest in another.
- Tokens, sessions, the locale, notifications and the audit trail are the account's, so they never leak from one
  organization into the other.
- Scoping is a comparison with one column, and suspending or deleting an organization affects exactly its accounts.

An email is unique within an organization, deleted users included, and among the users without an organization
(see [The User model](../users/00-user-model.md#unique-emails)). The same email may exist in any number of
organizations.

Users without an organization (`organization_id` is `null`) are api-core's own, internal users, such as super admins
(see [Acting organization](04-acting-organization.md)).

## The Organization model

`Core\Models\Organization` is a soft-deletable model with the `organizations` table:

| Column           | Type              | Description                                                            |
|------------------|-------------------|------------------------------------------------------------------------|
| `id`             | bigint            | The key, which `organization_id` columns refer to.                     |
| `slug`           | string, unique    | Names the organization at login (`organization` field).                |
| `name`           | string, indexed   | The display name.                                                      |
| `metadata`       | JSON, nullable    | Free-form data of the application, cast to an array.                   |
| `activated_at`   | timestamp         | When it was activated, set by the application. api-core doesn't check it. |
| `deactivated_at` | timestamp         | Set when it is deactivated: its users can't sign in.                   |
| `suspended_at`   | timestamp         | Set when it is suspended: its users can't sign in.                     |
| `created_at`, `updated_at`, `deleted_at` | timestamps | `deleted_at` is set by soft-deleting.                    |

Only `slug` and `name` are mass-assignable; the other columns are set with `forceFill()`:

```php
use Core\Models\Organization;

$organization = Organization::query()->create(['slug' => 'acme', 'name' => 'Acme']);
$organization->forceFill(['activated_at' => now()])->save();
```

Its methods are `users()` (a `HasMany` of its users), `isSuspended()`, `isDeactivated()` and `orgId()`, which returns
its own id, so an organization is in its own tenant context. Its query builder is `Core\Queries\OrganizationQuery`,
whose `index($user)` lists the organization the user acts in, or all of them for a super admin acting in all
organizations (see [Acting organization](04-acting-organization.md)).

api-core ships no routes for organizations. The default roles hold `viewAny`, `view` and `update` permissions on
`Organization` for an application's own controller (see [Configuring roles](../roles/01-configuring-roles.md)).

## Suspending and deactivating

`User::canSignIn()` is false for the users of a suspended (`suspended_at`) or deactivated (`deactivated_at`)
organization. Login refuses them with `422` and `This account is suspended.`, their access tokens are refused with
`401` and their sessions are signed out. Clearing the column lets them sign in again: tokens that haven't expired
work again, while signed-out sessions stay signed out. See [The User model](../users/00-user-model.md#suspension).

```php
$organization->forceFill(['suspended_at' => now()])->save();
```

## Deleting and restoring

Soft-deleting an organization soft-deletes its users at the same time, with the organization's `deleted_at`. Users
deleted earlier keep their own `deleted_at`. Restoring the organization restores the users whose `deleted_at` equals
the organization's, so the ones deleted before it stay deleted:

```php
$organization->delete();   // its users get the same deleted_at
$organization->restore();  // the users deleted with it come back
```

Each step is one `UPDATE` query, so no model events of the users are fired, and observers or listeners of `User`
don't see these changes. Since the users are deleted, their login fails as unknown credentials
(`These credentials do not match our records.`), not as suspended, and their tokens stop working.

Force-deleting an organization force-deletes its users first, deleted ones included, one by one through their
models, so their model events run: each hands the accounts linked to it in other organizations over to the oldest of
them, as when the user alone is force-deleted (see [Linked accounts](../auth/06-linked-accounts.md)), and the
person's other accounts stay linked. The `users.organization_id` foreign key (`on delete cascade`) only backs it up.

## Related

- [Current organization](01-current-organization.md)
- [Resolving the organization](02-resolving-the-organization.md)
- [Organization-scoped models](03-organization-scoped-models.md)
- [Acting organization](04-acting-organization.md)
- [The User model](../users/00-user-model.md)
- [Linked accounts](../auth/06-linked-accounts.md)
- [Login](../auth/01-login.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
