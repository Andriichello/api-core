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

## 4. Configure

Point Laravel's user provider to api-core's user, and the links of its emails to the SPA's pages:

```env
AUTH_MODEL=Core\Models\User
API_CORE_PASSWORD_RESET_URL=https://app.example.com/reset-password
API_CORE_EMAIL_VERIFICATION_URL=https://app.example.com/verify-email
API_CORE_INVITATION_URL=https://app.example.com/accept-invitation
```

To change more, such as the roles, publish the config with `php artisan vendor:publish --tag=api-core-config` (see
[Configuration](03-configuration.md)).

## 5. Migrate and seed the roles

```bash
php artisan migrate
```

Call api-core's seeder from the application's `database/seeders/DatabaseSeeder.php`:

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
- `password.updated` must come after `auth:sanctum` in the same list. It reads the signed-in user, and Laravel
  reorders only the middleware of its priority list, which `password.updated` isn't in. Placed before `auth:sanctum`,
  such as on an outer group around routes, which add `auth:sanctum` themselves, it runs before authentication, sees
  no user of a token and lets the request through.
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

## Related

- [Requirements](01-requirements.md)
- [Configuration](03-configuration.md)
- [Upgrading](08-upgrading.md)
- [Migrations](../database/00-migrations.md)
- [Middleware](../http/03-middleware.md)
- [Configuring roles](../roles/01-configuring-roles.md)
