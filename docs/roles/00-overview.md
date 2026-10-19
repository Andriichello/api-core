# Roles

Users get their abilities from roles, which hold permissions to act on models. api-core builds on
spatie/laravel-permission, defines the roles of an application in config, ranks them, and adds a super admin above
all of them. Read this first, then [Configuring roles](01-configuring-roles.md) to define your own.

## spatie/laravel-permission

`Core\Models\User` uses spatie's `HasRoles` trait, so the package's API works on users as usual (`assignRole()`,
`syncRoles()`, `hasRole()`, `getRoleNames()`, `can()`), with its `roles`, `permissions` and pivot tables, which
api-core's migrations create. api-core adds `Core\Models\Traits\ChecksRolesAndPermissions` on top (see
[Ranking roles](03-ranking-roles.md)).

Roles are attached to an account, and every account belongs to one organization, so a role is a role in that
organization: a person who is an admin in one organization and a guest in another has two accounts with those roles
(see [Organizations](../organizations/00-overview.md#one-organization-per-account)).

## UserRole

`Core\Enums\UserRole` names api-core's roles:

| Case                   | Value         | Role                                                              |
|------------------------|---------------|-------------------------------------------------------------------|
| `UserRole::SuperAdmin` | `super-admin` | api-core's own role, always present, above all others.            |
| `UserRole::Admin`      | `admin`       | A default role of `api-core.roles`.                               |
| `UserRole::User`       | `user`        | A default role of `api-core.roles`.                               |
| `UserRole::Guest`      | `guest`       | A default role of `api-core.roles`; may only view (see below).    |

An application may replace `admin`, `user` and `guest` with roles of its own, named as it likes (see
[Configuring roles](01-configuring-roles.md)). Wherever api-core takes a role, it takes a `UserRole` or a name.

## The super admin

The `super-admin` role is built in. `RoleRepository::sync()` always creates it, it ranks above every configured role,
and it has no permissions, since it needs none:

- policies skip the permission checks for super admins, and a permission that isn't defined at all is met only by
  them (so `restore` and `forceDelete`, unless a role lists them, are theirs alone);
- a super admin may name another organization to act in, and one without an organization acts in all of them (see
  [Acting organization](../organizations/04-acting-organization.md));
- a super admin may switch to other people's accounts (see [Switching accounts](../auth/05-switching-accounts.md)).

Give the role only to the people who operate the application, usually on accounts without an organization:

```php
use Core\Enums\UserRole;

$user->assignRole(UserRole::SuperAdmin->value);
```

## The guest

`CrudPolicy` lets a user create, update, delete, restore or force-delete models only when they have a role other than
`guest`, whatever their permissions. A user with only the `guest` role, or with no role at all, may at most view.
This holds for the name `guest` even when an application defines its own roles.

## Permission names

A permission is named `{action} {morph}`, where the morph is the model's alias in the morph map or, for a model
outside of it, the last part of its namespace and the plural of its class name, in kebab case:

| Model                              | Example permission            |
|------------------------------------|-------------------------------|
| `Core\Models\User`                 | `update models.users`         |
| `Core\Models\Organization`         | `view models.organizations`   |
| `Spatie\Permission\Models\Role`    | `attachUser models.roles`     |
| `App\Models\Invoice`               | `viewAny models.invoices`     |

The CRUD policies check the permission of the request's ability on their model, such as `update models.invoices` for
`PATCH /api/invoices/{id}` (see [Base policy](../http/policies/00-base-policy.md)). In your own code, check one with
spatie's `can()` or with `canOn()`, which builds the name from the class:

```php
$user->can('update models.invoices');
$user->canOn('update', Invoice::class);
```

Both check the permissions the user has, so they are false for a super admin, who has none: check `isSuperAdmin()`
first, as the policies do.

## The default roles

| Role          | Rank | Permissions                                                                                       |
|---------------|------|---------------------------------------------------------------------------------------------------|
| `super-admin` | 0    | None; needs none.                                                                                 |
| `admin`       | 1    | `User`: `all`, `attachRole`, `attachAnyRole`, `detachRole`; `Organization`: `viewAny`, `view`, `update`; `Role`: `attachUser`, `attachAnyUser`, `detachUser` |
| `user`        | 2    | `User`: `viewAny`, `view`; `Organization`: `viewAny`, `view`                                      |
| `guest`       | 3    | `User`: `viewAny`, `view`; `Organization`: `viewAny`, `view`                                      |

## Related

- [Configuring roles](01-configuring-roles.md)
- [Syncing roles](02-syncing-roles.md)
- [Ranking roles](03-ranking-roles.md)
- [Base policy](../http/policies/00-base-policy.md)
- [CRUD policy](../http/policies/01-crud-policy.md)
- [User policy](../http/policies/03-user-policy.md)
- [Base model](../models/00-base-model.md)
- [Managing users](../users/01-managing-users.md)
