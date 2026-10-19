# Migrations

api-core brings the migrations of all the tables it uses, Laravel's own ones included, in place of the stock ones of
a new application. It doesn't load them from `vendor`: the application publishes them into `database/migrations` and
runs them with its own.

## Publishing

1. Delete the stock migrations of a new Laravel application, which create the same tables:
   `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php` and
   `0001_01_01_000002_create_jobs_table.php`. Delete them before publishing, since api-core's migrations start with the
   same `0001_01_01_00000` prefix.
2. Don't keep Sanctum's `create_personal_access_tokens_table` migration, which `php artisan install:api` publishes,
   don't publish spatie/laravel-permission's migration, and don't create Laravel's notifications table: api-core has
   its own of each.
3. Publish and run:

   ```bash
   php artisan vendor:publish --tag=api-core-migrations
   php artisan migrate
   ```

The migrations keep their names when published. When an upgrade of api-core adds migrations, publish again: files
that exist already are skipped, so only the new ones are copied. Then run `php artisan migrate`.

## Tables

| Table | Migration | Contents |
|-------|-----------|----------|
| `organizations` | `0001_01_01_000000_create_organizations_table` | Tenants: unique `slug`, `name`, `metadata`, `activated_at`, `deactivated_at`, `suspended_at`; soft deletes. |
| `users` | `0001_01_01_000001_create_users_table`, `…_add_main_account_id_to_users_table`, `…_add_password_updated_at_to_users_table`, `…_add_unique_email_of_users_without_organization` | Accounts: `organization_id`, `main_account_id`, `name`, `email`, `password`, `password_updated_at`, `timezone` (`UTC`), `locale` (`en`), `metadata`, `email_verified_at`, `suspended_at`, `last_login_at`; soft deletes. |
| `password_reset_tokens` | `0001_01_01_000002_create_password_reset_tokens_table` | Password reset tokens by email. |
| `sessions` | `0001_01_02_000000_create_sessions_table` | Laravel's `database` session driver. |
| `cache`, `cache_locks` | `0001_01_02_000001_create_cache_table` | Laravel's database cache. |
| `jobs`, `job_batches`, `failed_jobs` | `0001_01_02_000002_create_jobs_table` | Laravel's database queue. |
| `personal_access_tokens` | `2026_04_05_070504_create_personal_access_tokens_table` and three `add_*` migrations | Access tokens: `organization_id`, the tokenable, `impersonator_id`, `issuer_token_id`, `token`, `refresh_token`, `abilities`, `metadata`, `expires_at`, `refresh_expires_at`. |
| `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` | `2026_05_06_112359_create_permission_tables` | spatie/laravel-permission's tables, named by its `permission.table_names` config. |
| `files` | `2026_10_15_170050_create_files_table` | Files attached to models. |
| `settings` | `2026_10_18_173631_create_settings_table` | Settings of models, unique per model and key. |
| `audit_logs` | `2026_10_18_181849_create_audit_logs_table` | The audit log, without foreign keys, so it outlives what it names. |
| `sequences` | `2026_10_18_184014_create_sequences_table` | Sequences of numbers, unique per owner and key. |
| `notifications` | `2026_10_18_185200_create_notifications_table` | Laravel's database notifications. |

## Keys and indexes

- `users.organization_id` references `organizations` and cascades on delete; `users.main_account_id` references `users`
  and is set to `null` when the main account is deleted permanently.
- `personal_access_tokens.impersonator_id` references `users` and cascades on delete, so the tokens of switched
  accounts go with the user who switched, when that user is deleted permanently.
- `personal_access_tokens.issuer_token_id` is indexed, but has no foreign key: refreshing replaces the issuer and passes
  its switched tokens on, and logging out deletes them.
- An email is unique within an organization: `unique(organization_id, email)`. Deleted users keep counting, which is
  why inviting the email of a deleted user restores that user.
- An email is unique among the users without an organization too: since `NULL`s are all distinct in a unique index,
  `users_organization_key_email_unique` covers `(coalesce(organization_id, 0), email)`. The first index
  (`users_organization_id_email_unique`) stays, as MySQL needs it for the foreign key of `organization_id`.

## Databases

The expression index of `…_add_unique_email_of_users_without_organization` needs MySQL 8.0.13 or newer, PostgreSQL or
SQLite. MariaDB doesn't support it, so api-core doesn't run on MariaDB.

The same migration fails on a database where two users without an organization already share an email. Find them
before upgrading an existing database:

```sql
select email, count(*) from users where organization_id is null group by email having count(*) > 1;
```

## The application's tables

The application's own migrations follow api-core's conventions:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained();
    $table->foreignId('client_id')->constrained();
    $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('number');
    $table->string('status')->default('draft');
    $table->unsignedInteger('total')->default(0);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['organization_id', 'number']);
});
```

## Related

- [Installation](../getting-started/02-installation.md)
- [Requirements](../getting-started/01-requirements.md)
- [Upgrading](../getting-started/08-upgrading.md)
- [Factories and seeders](01-factories-and-seeders.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Tracking the creator](../models/01-tracking-creator.md)
- [Re-inviting deleted users](../users/02-re-inviting-deleted-users.md)
