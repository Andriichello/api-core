# Upgrading

What to do when deploying an application with api-core or moving it to a newer api-core, and the behavior, which
existing data, clients and listeners meet. Read it before running `composer update` on api-core in production.

## Deployment steps

```bash
composer install --no-dev
php artisan vendor:publish --tag=api-core-migrations
php artisan migrate --force
php artisan db:seed --class="Core\Database\Seeders\RolesAndPermissionsSeeder" --force
php artisan config:cache
```

- **Migrations** keep their names when published, so publishing again copies only the ones added since, and leaves
  the published ones as they are.
- **Roles** are synced from `api-core.roles` on every run: missing roles and permissions are created, and each
  configured role gets exactly its permissions. Roles that aren't configured, and the users' roles, are kept.
- **Config** is merged when it is cached, so cache it again. New options of api-core reach an application, which
  published its config, since nested options are merged. Lists aren't: an application with its own `roles` list
  doesn't get permissions, which a newer api-core adds to its default list, until it adds them itself.

## Database

- **Unique emails without an organization.** The migration
  `2026_10_18_203729_add_unique_email_of_users_without_organization` adds a unique index on
  `(coalesce(organization_id, 0), email)`. It fails, when two accounts without an organization already share an email
  (deleted accounts count too), and always on MariaDB. Find the duplicates first:

  ```sql
  select email, count(*) from users where organization_id is null group by email having count(*) > 1;
  ```

- **Dropped columns.** The migration `2026_10_19_220836_drop_unused_columns_of_sessions_and_password_reset_tokens`
  drops `sessions.token_id` and `token_type`, and `password_reset_tokens.used_at` and `expires_at`, which nothing used.
- **Required password updates.** `password.updated` answers 403 to every user, whose `password_updated_at` is `null`,
  until they change the password. Existing users get `null` from the migration, which adds the column, and so does
  any password set by someone else (a seeder, an admin). Stamp the passwords, which their owners chose, in a migration
  of your own (`update users set password_updated_at = now() where …`). For linked accounts, the main account's value
  counts.
- **Audit log events** longer than 32 characters (the column's length) make `AuditLog::record()` throw a
  `LogicException` on every database, before anything is written.

## Accounts and sessions

- **Deleted organizations.** Soft-deleting an organization soft-deletes its users with the same `deleted_at`, and
  restoring it restores just those users. While it is deleted, their login fails as unknown credentials (`email`:
  "These credentials do not match our records."), not as `This account is suspended.`, which a suspended or
  deactivated organization's users get.
- **Switched sessions.** A session, which a user switched to another account, holds that user's password hash, and
  `AuthenticateSession` signs it out without one. Sessions switched by an older api-core lack it, so they are signed
  out once, on their next request.
- **Re-invited users.** Inviting the email of a deleted user of the organization restores that user. `suspended_at`
  stays as it was, so a suspended user remains unable to sign in after accepting.
- **Switched tokens.** A token issued by switching may not switch again (`403`), not even when its account may switch
  by itself, such as a main account, which a super admin switched to. Logging out of a token deletes the tokens it
  switched to; the migration `2026_10_19_215548` adds the `issuer_token_id`, which links them, so publish and run it.
  Tokens switched before it have none, and live until they expire.
- **File and setting controllers** need the policy of their model: one without its own constructor, which got a plain
  `CrudPolicy` and checked no permissions, or one given another model's policy, now fails every request with a
  `LogicException`.
- **Locales.** `locale` of `PATCH auth/me` and of the user routes must be one of `api-core.locales` (422 otherwise),
  and emails to a user are in the user's own locale, when it is supported, rather than in the sender's.
- **Own accounts in other organizations.** `UserController` lists and shows the person's own accounts in other
  organizations, but changing, deleting or inviting them again answers `403`, unless the user acts in all
  organizations.

## Includes and appends

- An include nested under a relation, whose entry in `$allowedIncludes` names no resource (`['client']` rather than
  `['client' => ClientResource::class]`), is a 422, as is an append nested under such a relation. Map the relation to
  a resource, which allows them.
- Included records are loaded through their queries' `index()`: a related record, which the user may not list, comes
  back as `null` (a single relation) or is left out (a list).

## Auth events

- Logins, failed logins, logouts and switches are recorded in the audit log only with `API_CORE_AUDIT_AUTH_EVENTS=true`.
- A failed login fires Laravel's `Illuminate\Auth\Events\Failed` with the email only in its credentials, never the
  password. Its `user` is `null` for unknown credentials and the account for a suspended one. Listeners of the
  application see these events too.

## Errors

Outside debug mode, a missing model (a `ModelNotFoundException`, such as one of `findOrFail()` or of a route binding)
or a missing enum case of a route answers a plain 404 with `{"message": "Not found."}`, without the class or the id.
Clients, which matched Laravel's "No query results for model" message, need to match the status instead.

## Related

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Migrations](../database/00-migrations.md)
- [Syncing roles](../roles/02-syncing-roles.md)
- [Required password update](../auth/10-required-password-update.md)
- [Organizations](../organizations/00-overview.md)
- [Includes](../http/resources/02-includes.md)
- [Errors](../http/02-errors.md)
