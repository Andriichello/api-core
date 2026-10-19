# Factories and seeders

api-core's models come with factories, which the application's tests and seeders can use, and with
`RolesAndPermissionsSeeder`, which creates the roles. The factories live in `Core\Database\Factories` and the models
find them on their own, so `User::factory()` works as with the application's models.

The factories use Faker (`fakerphp/faker`), which a Laravel application requires for development, so they are meant
for tests and local seeding, not for production.

## Factories

| Factory | Makes | States |
|---------|-------|--------|
| `User::factory()` | A user without an organization, with a verified email, the password `password` and `password_updated_at` set. | `unverified()`, `requiringPasswordUpdate()` |
| `Organization::factory()` | An activated organization with a unique slug. | `inactive()`, `deactivated()`, `suspended()` |
| `File::factory()` | The record of a text file of a new user, which isn't stored on the disk. | `inCollection($collection)`, `stored($contents = 'Contents of the file.', $mimeType = 'text/plain')` |
| `Setting::factory()` | A stored setting of a new user. | |
| `Sequence::factory()` | A sequence of a new organization, with a random key and next number. | |
| `AuditLog::factory()` | A manual entry without a causer, subject or organization. | `causedBy($model)`, `onSubject($model)` |

The models are in `Core\Models`. Give a factory's model another owner with Laravel's `for()`:

```php
use Core\Models\File;
use Core\Models\Organization;
use Core\Models\Sequence;
use Core\Models\Setting;
use Core\Models\User;

$organization = Organization::factory()->create();

$user = User::factory()->for($organization)->create();
$unverified = User::factory()->for($organization)->unverified()->create();

$file = File::factory()->for($invoice, 'fileable')->stored('%PDF-1.7 ...', 'application/pdf')->create();
$setting = Setting::factory()->for($client, 'model')->create(['key' => 'invoice.prefix', 'value' => 'INV']);
$sequence = Sequence::factory()->for($client, 'model')->create(['key' => 'invoices', 'next_number' => 42]);
```

`stored()` writes the contents to the disk of `api-core.files.disk`, so fake that disk in the test:

```php
Storage::fake(config('api-core.files.disk'));
```

Both `causedBy()` and `onSubject()` also set the entry's `organization_id` from the model; `onSubject()` keeps the one
set before when the subject has no organization.

## Models of an organization

The application's models with `BelongsToOrganization` see only the records of the current organization, and none
without one. In a test that works with them directly, rather than through a request, set it:

```php
use Core\Helpers\CurrentOrganization;

$invoice = CurrentOrganization::run($organization, fn () => Invoice::factory()->create());
```

Without a current organization, a record is saved with the `organization_id` it is given (and refused without one),
but reading it back needs the organization too. A request through the `organization` middleware sets it from the
signed-in user.

## Seeders

`Core\Database\Seeders\RolesAndPermissionsSeeder` syncs the roles and their permissions with `api-core.roles`. It may
run again whenever the roles change: it creates what is missing, gives each role exactly the configured permissions and
keeps the roles of users. Call it from the application's `DatabaseSeeder`:

```php
namespace Database\Seeders;

use Core\Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
```

`Core\Database\Seeders\DatabaseSeeder` is api-core's own, for working on api-core itself; the application writes
its own.

## A feature test

With the application's `api-core.roles` giving admins the permissions on invoices:

```php
use Core\Database\Seeders\RolesAndPermissionsSeeder;
use Core\Models\Organization;
use Core\Models\User;
use Laravel\Sanctum\Sanctum;

public function testAdminListsInvoices(): void
{
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->for(Organization::factory())->create();
    $admin->assignRole('admin');

    Sanctum::actingAs($admin);

    $this->getJson('/api/invoices')->assertOk();
}
```

## Related

- [Migrations](00-migrations.md)
- [Syncing roles](../roles/02-syncing-roles.md)
- [Current organization](../organizations/01-current-organization.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [The user model](../users/00-user-model.md)
- [Files](../files/00-overview.md)
- [Sequences](../models/04-sequences.md)
- [Writing tests](../development/02-writing-tests.md)
