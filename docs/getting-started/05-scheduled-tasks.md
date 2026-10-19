# Scheduled tasks

Two of api-core's models collect rows, which nothing deletes during requests: access tokens, whose access and refresh
tokens have both expired, and audit log entries older than their retention. Schedule Laravel's `model:prune` for both.

## Scheduling the pruning

`model:prune` looks only in the application's `app/Models`, unless it is given the models, so name api-core's ones.
In `routes/console.php`:

```php
use Core\Models\AccessToken;
use Core\Models\AuditLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [AccessToken::class, AuditLog::class],
])->daily();
```

Add the application's own prunable models to the list, or schedule a second `model:prune` without `--model` for them.
The scheduler itself needs the usual cron entry, which runs `php artisan schedule:run` every minute.

To see what would be deleted, without deleting it:

```bash
php artisan model:prune --model="Core\Models\AccessToken" --model="Core\Models\AuditLog" --pretend
```

## Access tokens

A row of `personal_access_tokens` holds an access token and its refresh token:

| Column               | Set from                                                  | Default lifetime |
|----------------------|-----------------------------------------------------------|------------------|
| `expires_at`         | `api-core.auth.access_token_lifetime` (`API_CORE_ACCESS_TOKEN_LIFETIME`)   | 8 hours  |
| `refresh_expires_at` | `api-core.auth.refresh_token_lifetime` (`API_CORE_REFRESH_TOKEN_LIFETIME`) | 14 days  |

`AccessToken::prunable()` selects the rows, whose access token has expired and whose refresh token has expired too or
doesn't exist (a token of a switched account has none). Rows go away without pruning, too: logging out deletes the
token, and exchanging a refresh token deletes its row and creates a new one.

### Why not `sanctum:prune-expired`

Sanctum's command deletes every token, whose `expires_at` passed more than `--hours` (24 by default) ago, and knows
nothing of refresh tokens. An access token expires after 8 hours, while its refresh token stays valid for 14 days, so
the command would delete refresh tokens, which clients still hold, and sign those clients out. Don't schedule it.

## Audit log

`AuditLog::prunable()` selects the entries, whose `created_at` is older than `api-core.audit.retention_days`
(`API_CORE_AUDIT_RETENTION_DAYS`) days. With `null`, the default, it selects none, and the log is kept forever.

```env
API_CORE_AUDIT_RETENTION_DAYS=365
```

Entries of the audit log can't be deleted one by one: deleting an `AuditLog` model throws a `LogicException`. Pruning
still works, as both models use `MassPrunable`, which deletes with a query and fires no model events.

## Related

- [Tokens](../auth/02-tokens.md)
- [Reading and pruning the audit log](../audit/04-reading-and-pruning.md)
- [Configuration](03-configuration.md)
- [Upgrading](08-upgrading.md)
