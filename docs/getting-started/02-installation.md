# Installation

The steps to add api-core to a Laravel 13 application, from requiring the package to the first signed-in super admin.
Check the [requirements](01-requirements.md) first.

## 1. Require the package

api-core is installed from its Git repository. Add the repository to the application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Andriichello/api-core"
        }
    ]
}
```

```bash
composer require andriichello/api-core:dev-dev
```

Package discovery registers api-core's service providers, along with Sanctum's and spatie's.

## 2. Replace the stock migrations

api-core brings its own tables of users, password reset tokens, sessions, cache, jobs and access tokens, so delete
Laravel's three stock migrations, which create them, **before** publishing api-core's:

```bash
rm database/migrations/0001_01_01_000000_create_users_table.php \
   database/migrations/0001_01_01_000001_create_cache_table.php \
   database/migrations/0001_01_01_000002_create_jobs_table.php
php artisan vendor:publish --tag=api-core-migrations
```

api-core's migrations start with the same `0001_01_01_00000` prefix, so don't delete by that pattern once they are
published. Publishing copies them under their own names (see [Migrations](../database/00-migrations.md)). Don't
publish spatie's `create_permission_tables` migration, nor keep Sanctum's `create_personal_access_tokens_table`.

## 3. Add the API routes file

An application without `routes/api.php` creates it with:

```bash
php artisan install:api --without-migration-prompt
```

It also registers the file in `bootstrap/app.php`. Since api-core's access token migration is already published, the
command doesn't publish Sanctum's; if you ran it before step 2, delete that migration.

The command adds `laravel/sanctum` to the application's `composer.json`, which api-core requires anyway, and asks to
add Sanctum's `HasApiTokens` trait to the user model. `Core\Models\User` already uses it, so there is nothing to add.

## 4. Configure

Point Laravel's user provider to api-core's user, and the links of its emails to the SPA's pages:

```env
AUTH_MODEL=Core\Models\User
API_CORE_PASSWORD_RESET_URL=https://app.example.com/reset-password
API_CORE_EMAIL_VERIFICATION_URL=https://app.example.com/verify-email
API_CORE_INVITATION_URL=https://app.example.com/accept-invitation
```

Laravel's skeleton has a user model of its own, which api-core's replaces. Delete it and its factory, and import
api-core's in `config/auth.php`, whose user provider falls back to it when `AUTH_MODEL` isn't set:

```bash
rm app/Models/User.php database/factories/UserFactory.php
```

```php
// config/auth.php
use Core\Models\User;
```

Tests make users with `Core\Models\User::factory()` (see
[Factories and seeders](../database/01-factories-and-seeders.md)).

To change more, such as the roles, publish the config with `php artisan vendor:publish --tag=api-core-config` (see
[Configuration](03-configuration.md)).

## 5. Migrate and seed the roles

```bash
php artisan migrate
```

Call api-core's seeder from the application's `database/seeders/DatabaseSeeder.php`, in place of the skeleton's
`User::factory()` call, whose model is gone:

```php
use Core\Database\Seeders\RolesAndPermissionsSeeder;

public function run(): void
{
    $this->call(RolesAndPermissionsSeeder::class);
}
```

```bash
php artisan db:seed
```

It creates the roles of `api-core.roles` and gives them exactly their permissions. Run it again whenever that list
changes, e.g. on every deployment (see [Syncing roles](../roles/02-syncing-roles.md)).

## 6. Register the routes

In `routes/api.php`:

```php
use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Controllers\NotificationController;
use Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(fn () => AuthController::routes());

Route::middleware(['auth:sanctum', 'password.updated'])->group(function () {
    UserController::routes();
    NotificationController::routes();

    Route::middleware('organization')->group(function () {
        // The application's own routes
    });
});
```

- The auth routes live under `/api/auth/` (see [Authentication](../auth/00-overview.md)). Don't put
  `password.updated` on them, since a person replaces a required password through them.
- `password.updated` reads the signed-in user, so it runs after authentication wherever a route lists it: api-core
  ranks it after `auth:sanctum` in the middleware priority (see [Middleware](../http/03-middleware.md#order)).
- `organization` sets the organization, in which the request acts. `UserController::routes()` adds it to its own
  routes.

## 7. Create the first super admin

A super admin without an organization acts in all organizations, and in a single one, when a request names it in the
`X-Organization-Id` header. Create one in `php artisan tinker`:

```php
$admin = new Core\Models\User(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'a-long-secret']);
$admin->forceFill(['email_verified_at' => now(), 'password_updated_at' => now()])->save();
$admin->assignRole('super-admin');
```

`password_updated_at` marks the password as chosen by its owner. A password set by anyone else leaves it empty, and
`password.updated` then answers 403 until the person changes the password (see
[Required password update](../auth/10-required-password-update.md)).

Organizations are created in code. To invite the first admin of one, who chooses a password when accepting:

```php
$acme = Core\Models\Organization::query()->create(['slug' => 'acme', 'name' => 'Acme']);

app(Core\Repositories\UserRepository::class)
    ->invite(['name' => 'Ann Lee', 'email' => 'ann@acme.test'], $acme->id, ['admin']);
```

The invitation email links to `API_CORE_INVITATION_URL` (see [Invitations](../auth/11-invitations.md)).

## 8. Optional features

| Feature                        | Set up in                                                                  |
|--------------------------------|----------------------------------------------------------------------------|
| A SPA signing in with sessions | [SPA with sessions](04-spa.md)                                             |
| Pruning tokens and audit logs  | [Scheduled tasks](05-scheduled-tasks.md)                                   |
| CRUD endpoints of your models  | [Building a feature](06-building-a-feature.md)                             |
| Files of models                | [File routes](../files/02-file-routes.md)                                  |
| Settings of models             | [Setting routes](../settings/02-setting-routes.md)                         |
| Restoring deleted records      | [Restoring](../http/controllers/03-restoring.md)                           |
| Locales                        | [Locale middleware](../localization/00-locale-middleware.md)               |
| The audit log                  | [Auditing requests](../audit/02-auditing-requests.md)                      |

## 9. AI agents

api-core ships a guideline and a skill for [Laravel Boost](https://laravel.com/docs/boost) in `resources/boost/`. In an
application, which uses Boost, install them with:

```bash
php artisan boost:install
```

and select `andriichello/api-core` among the third-party packages. The guideline joins the agents' guidelines (such as
`CLAUDE.md` or `AGENTS.md`): it tells agents where api-core's documents are installed
(`vendor/andriichello/api-core/docs/`) and the rules that prevent silent mistakes. The `api-core-development` skill,
which agents load when a task needs it, lists the steps of adding a model with endpoints and maps tasks to the
documents. Boost keeps the selection in `boost.json`, so `php artisan boost:update` refreshes both.

- Without prompts (`--no-interaction`), `boost:install` takes the agents and the packages from `boost.json`, and
  selects no package that isn't listed there. List them first, then run `php artisan boost:install --no-interaction`:

  ```json
  {
      "agents": ["claude_code"],
      "packages": ["andriichello/api-core"],
      "cloud": false
  }
  ```

- Boost offers its Laravel Cloud integration selected, which adds the `deploying-to-cloud` skill. Deselect it, or set
  `"cloud": false` as above, for an application deployed elsewhere.
- Laravel's skeleton ships `CLAUDE.md` and `AGENTS.md` with notes for agents on installing PHP and Composer.
  `boost:install` replaces the notes in the file of each agent you select (`CLAUDE.md` for Claude Code, `AGENTS.md`
  for most others); delete the other file, when none of your agents reads it.

## Related

- [Requirements](01-requirements.md)
- [Configuration](03-configuration.md)
- [Upgrading](08-upgrading.md)
- [Migrations](../database/00-migrations.md)
- [Middleware](../http/03-middleware.md)
- [Configuring roles](../roles/01-configuring-roles.md)
