# Requirements

What an application needs to run api-core: the PHP version and extensions, the packages it brings along, a supported
database and a few services. Check them before [installing](02-installation.md).

## PHP

api-core requires PHP 8.5 (`"php": "^8.5"` in its `composer.json`) with these extensions:

| Extension  | Why                                                                                                   |
|------------|-------------------------------------------------------------------------------------------------------|
| `pdo`      | the database, through the PDO driver of your database (`pdo_mysql`, `pdo_pgsql` or `pdo_sqlite`)      |
| `fileinfo` | `HasFiles` recognizes the type of an uploaded or generated file from its contents, not from its name |

Laravel itself needs `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session` and `tokenizer`.

## Packages

Composer installs these along with api-core, so an application doesn't require them itself:

| Package                      | Version  | Used for                                                                   |
|------------------------------|----------|----------------------------------------------------------------------------|
| `laravel/framework`          | `^13.0`  | everything                                                                 |
| `laravel/sanctum`            | `^4.3`   | access tokens, and sessions of a SPA                                       |
| `spatie/laravel-permission`  | `^7.4`   | the roles and permissions tables, and permission checks                    |

api-core ships its own migrations of these packages' tables (the access tokens get extra columns, such as the refresh
token), so an application publishes neither package's migrations.

## Databases

| Database             | Support                                                                                            |
|----------------------|----------------------------------------------------------------------------------------------------|
| MySQL 8.0.13 or newer | Supported. api-core's tests run on it.                                                           |
| PostgreSQL           | Supported.                                                                                         |
| SQLite               | Supported, and the default of api-core's tests. It ignores row locks, see below.                   |
| MariaDB              | Not supported.                                                                                     |

Accounts in different organizations may share an email, so the `users` table is unique on the organization and the
email. A plain unique index treats every `null` as different, which would let two accounts without an organization
(such as super admins) share an email. The migration `2026_10_18_203729_add_unique_email_of_users_without_organization`
therefore adds a unique index over an expression, `(coalesce(organization_id, 0), email)`. MySQL supports indexes over
expressions from 8.0.13 on, and MariaDB doesn't support them, so that migration fails there.

SQLite ignores `lockForUpdate()`. `Sequence::next()` relies on that row lock to hand out each number once when two
requests allocate at the same time, so use SQLite for tests and local work, not for an application with concurrent
writes.

## Services

- **Mail**: password reset links, email verification links and invitations are emailed during the request, not queued.
- **A private disk** for files (`API_CORE_FILES_DISK`, `local` by default), since files are downloaded through the
  API, which checks access first.
- **A queue worker**, when the application dispatches `AsyncJob`s.
- **The scheduler**, to prune expired tokens and old audit log entries (see [Scheduled tasks](05-scheduled-tasks.md)).
- **A SPA on the same site**, if it signs in with sessions (see [SPA with sessions](04-spa.md)).

## Related

- [Overview](00-overview.md)
- [Installation](02-installation.md)
- [Migrations](../database/00-migrations.md)
- [Sequences](../models/04-sequences.md)
- [Files](../files/00-overview.md)
