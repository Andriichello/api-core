## API Core

API Core is an opinionated Laravel API skeleton designed for multi-tenant applications. It provides an architectural
foundation with built-in support for global/tenant data scoping, advanced session-to-token bridging, and dynamic CRUD
capabilities.

The documentation is in [docs](docs/00-index.md).

### Requirements

- PHP 8.5 with the `pdo` and `fileinfo` extensions, and Laravel 13.
- MySQL 8.0.13 or newer, PostgreSQL or SQLite. One of the migrations adds a unique index over an expression
  (`coalesce(organization_id, 0)`), which MariaDB doesn't support.

### Installing in an application

1. Add the VCS repository `https://github.com/Andriichello/api-core` to the application's `composer.json`, and require
   `andriichello/api-core:dev-dev`.
2. Delete Laravel's stock `0001_01_01_00000*` migrations, and don't keep Sanctum's migration from `install:api`:
   api-core brings its own tables of users, password resets, sessions, cache, jobs and access tokens.
3. Publish the migrations with `php artisan vendor:publish --tag=api-core-migrations`, and the config with
   `--tag=api-core-config` to change it. Lists in the application's `config/api-core.php` replace api-core's as a whole.
4. Set `AUTH_MODEL=Core\Models\User`, and point `API_CORE_PASSWORD_RESET_URL`, `API_CORE_EMAIL_VERIFICATION_URL` and
   `API_CORE_INVITATION_URL` to the SPA's pages. Delete the skeleton's `app/Models/User.php` and its `UserFactory`, and
   import `Core\Models\User` in `config/auth.php`.
5. Run `php artisan migrate`, and call `Core\Database\Seeders\RolesAndPermissionsSeeder` from the application's
   `DatabaseSeeder`, in place of its `User::factory()` call.
6. Register the auth routes in `routes/api.php`: `Route::prefix('auth')->group(fn () => AuthController::routes());`.
   Put the `password.updated` middleware on the application's own routes (not on the auth routes).
7. For a SPA: `$middleware->statefulApi()`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` for subdomains, CORS
   `supports_credentials => true`, and a `GET /sanctum/csrf-cookie` call before the login.
8. For files: a private disk in `API_CORE_FILES_DISK` (`local` by default), the application's fileable models in the
   morph map, and a `FileController` subclass per fileable model, whose `routes()` are registered under the
   application's auth middleware. A route nested deeper, such as `profiles/{profile}/invoices/{invoice}/files`, names
   the outer parameters in the controller's `$parents` (the fileable's BelongsTo relations).
9. For settings: `settingDefinitions()` on the model, and a `SettingController` subclass per model, registered the
   same way.
10. For restoring: `'restore' => RestoreRequest::class` in a controller's `$operations`, a `POST {uri}/{id}/restore`
    route, and the `restore` action in the roles' permissions (see below).
11. For users: `UserController::routes()` for the users of an organization (inviting the email of a deleted user
    restores that user), and the auth routes for `accept-invitation`.
12. For locales: `api-core.locales` and the `locale` middleware on the API routes. For notifications:
    `NotificationController::routes()` and the `database` channel in the application's notifications.
13. For the audit log: the `Audited` trait on the models to audit, the `audit` middleware on the API routes (the auth
    routes included), `API_CORE_AUDIT_AUTH_EVENTS=true` to record logins, failed logins, logouts and account switches,
    and `php artisan model:prune --model="Core\Models\AuditLog"` scheduled, when `API_CORE_AUDIT_RETENTION_DAYS` is
    set.
14. Schedule `php artisan model:prune --model="Core\Models\AccessToken"` to delete expired tokens. Don't use
    `sanctum:prune-expired`, which would delete refresh tokens, which are still valid.
15. With Laravel Boost, run `php artisan boost:install` and select `andriichello/api-core`, which adds api-core's
    guideline and the `api-core-development` skill, pointing AI agents to the documents in
    `vendor/andriichello/api-core/docs/`. Without prompts, list the package under `packages` in `boost.json` first.

### Roles and permissions

`api-core.roles` lists the roles from the highest to the lowest, with the actions, which each of them has on a
resource (a model class). `all` stands for `viewAny`, `view`, `create`, `update` and `delete`, but not for `restore`
and `forceDelete`: a role, which may restore deleted records or delete them permanently, lists those actions
explicitly, or else only super admins may. `RolesAndPermissionsSeeder` syncs the database with the config.
