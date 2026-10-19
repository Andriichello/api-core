# Audit log

The audit log keeps who did what to which record, and when: changes of models, requests to the API, sign-ins and
events the app records itself. Use it when an app must be able to tell, after the fact, who changed or saw something.

## Sources

Every entry is a `Core\Models\AuditLog` in the `audit_logs` table, and its `source` tells what recorded it:

| Source | Constant | Recorded by |
|---|---|---|
| `model` | `AuditLog::SOURCE_MODEL` | The `Audited` trait, when a model is created, updated, deleted or restored (see [Auditing models](01-auditing-models.md)). |
| `request` | `AuditLog::SOURCE_REQUEST` | The `audit` middleware, after a request's response (see [Auditing requests](02-auditing-requests.md)). |
| `manual` | `AuditLog::SOURCE_MANUAL` | The app's code, with `AuditLog::record()` (see [Recording events](03-recording-events.md)). |
| `auth` | `AuditLog::SOURCE_AUTH` | Sign-ins, failed sign-ins, sign-outs and account switches, when enabled (see [Recording events](03-recording-events.md#authentication-events)). |

None of them is on by default: an app adds the trait to its models, the middleware to its routes, and turns on the
authentication events.

## The `audit_logs` table

| Column | Description |
|---|---|
| `id` | Key. |
| `organization_id` | Organization, in which the event happened, or `null`. |
| `causer_type`, `causer_id` | Who caused the event, usually the signed-in user, or `null`. |
| `subject_type`, `subject_id` | Record the event happened to, or `null`. |
| `source` | `model`, `request`, `manual` or `auth`. |
| `event` | What happened, such as `updated`, `post` or `exported`. At most 32 characters. |
| `name` | Name of the entry, such as `models.invoices.updated`, or a route's name. At most 191 characters. |
| `description` | Optional text. At most 255 characters. |
| `status_code` | Status of a request's response. |
| `changes` | JSON of the `old` and `new` values of a model's attributes. |
| `metadata` | JSON of anything else, such as the HTTP side of a request. |
| `created_at` | When it was recorded. Entries have no `updated_at`. |

`organization()`, `causer()` and `subject()` are the entry's relations. Indexes serve the lists of an organization by
time, of a subject and of a causer.

## Entries are immutable

Saving changes to an entry throws a `LogicException` ("Entries of the audit log can't be changed."), and so does
deleting one ("Entries of the audit log can't be deleted."). A log, which could be edited through the app, couldn't be
trusted. Old entries leave only by pruning (see [Reading and pruning](04-reading-and-pruning.md)).

## Entries outlive what they name

The table has no foreign keys: an entry stays, when the organization, the user or the record, which it names, is
deleted, since that's when the log is needed most. The ids stay in the entry, while `causer` and `subject` give `null`
for records, which no longer exist (or which are soft-deleted, as Laravel's `morphTo` leaves them out).

## Column limits

- `event` is refused rather than cut: an event longer than 32 characters throws a `LogicException`, on any database,
  and nothing is recorded. An event identifies what happened, so a cut one would be wrong rather than shorter.
- `name` is cut to 191 characters and `description` to 255, so a long route name or text never fails the recording.

## Setup

1. Run api-core's migrations, which create `audit_logs` (see [Migrations](../database/00-migrations.md)).
2. Add `Audited` to the models, whose changes to keep.
3. Add the `audit` middleware to the API routes, the auth routes included.
4. Set `API_CORE_AUDIT_AUTH_EVENTS=true` to record sign-ins and switches.
5. Set `API_CORE_AUDIT_RETENTION_DAYS` and schedule the pruning, unless entries are kept forever.

## Configuration

| Key | Env | Default | Description |
|---|---|---|---|
| `api-core.audit.retention_days` | `API_CORE_AUDIT_RETENTION_DAYS` | `null` | Days, for which entries are kept before pruning deletes them; `null` keeps them forever. |
| `api-core.audit.auth_events` | `API_CORE_AUDIT_AUTH_EVENTS` | `false` | Whether authentication events are recorded. |
| `api-core.audit.scopes` | | `['http']` | What the `audit` middleware records of a request, unless its parameters say. |
| `api-core.audit.except` | | `paths: []`, `names: ['sanctum.csrf-cookie']` | Requests the middleware doesn't record, by path or route name. |
| `api-core.audit.max_payload_size` | `API_CORE_AUDIT_MAX_PAYLOAD_SIZE` | `2048` | The maximum size of a recorded payload or error response, in bytes. |
| `api-core.audit.scrub_fields` | | `password`, `token`, `secret`, … | Fields of a payload, whose values are masked. |

The lists replace api-core's as a whole, when the app's `config/api-core.php` sets them.

## Related

- [Auditing models](01-auditing-models.md)
- [Auditing requests](02-auditing-requests.md)
- [Recording events](03-recording-events.md)
- [Reading and pruning](04-reading-and-pruning.md)
- [Scheduled tasks](../getting-started/05-scheduled-tasks.md)
- [Configuration](../getting-started/03-configuration.md)
- [Tracking the creator](../models/01-tracking-creator.md)
