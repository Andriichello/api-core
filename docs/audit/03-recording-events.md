# Recording events

`AuditLog::record()` writes an entry of the audit log from the app's own code, for events, which are neither a model's
change nor a request, such as an export or a sent email. api-core uses it for the authentication events too.

## Recording an event

```php
use Core\Models\AuditLog;

AuditLog::record('exported', $client, [
    'description' => 'Invoices of 2026 exported to CSV.',
    'metadata' => ['format' => 'csv', 'count' => 214],
]);
```

`record(string $event, ?Model $subject = null, array $attributes = []): static` saves and returns the entry. Without
attributes, it is filled in as follows:

| Attribute | Default |
|---|---|
| `causer` | The signed-in user. Pass `'causer' => $user` for another one, or `'causer' => null` for none. |
| `organization_id` | The subject's `orgId()`, else the current organization, else the causer's one. |
| `source` | `manual` |
| `name` | `{subject's morph alias}.{event}`, such as `models.clients.exported`, or the event without a subject. |

Any other column, such as `description`, `metadata`, `changes` or `status_code`, can be given in `$attributes`, which
also override the defaults above.

The entry is an ordinary insert: written in a transaction, it is rolled back with it. Record an event after the work it
describes succeeded, or within the same transaction:

```php
DB::transaction(function () use ($invoice, $user) {
    $invoice->update(['status' => 'void']);

    AuditLog::record('voided', $invoice, ['causer' => $user, 'description' => 'Duplicate of INV-2026-0041.']);
});
```

## Limits

- `event` must be at most 32 characters. A longer one throws a `LogicException`, and nothing is recorded, so keep events
  short verbs, such as `exported`, `sent` or `voided`.
- `name` is cut to 191 characters and `description` to 255.

## Authentication events

With `API_CORE_AUDIT_AUTH_EVENTS=true`, api-core records sign-ins, failed sign-ins, sign-outs and account switches, with
the `auth` source. They are off by default.

| Event | Subject | Causer | `metadata` |
|---|---|---|---|
| `login` | The account, which signed in, with a token or a session. | The account. | `ip`, `user_agent` |
| `login_failed` | The account, when the credentials match it, but it can't sign in (it or its organization is suspended, deactivated or deleted); none, when no account matched. | The account, or none. | `ip`, `user_agent`, `email` |
| `logout` | The account, which signed out. | The account. | `ip`, `user_agent`, and `impersonator_id` for a switched account |
| `switched` | The account switched to. | The user, who switched. | `ip`, `user_agent`, and `impersonator_id` when it isn't a switch back |

A failed sign-in records the email, but never the password. A wrong password for an existing email matches no account,
so it is recorded without a subject, like an unknown email, and the log doesn't tell which emails have accounts.

`LoginRequest` fires Laravel's `Illuminate\Auth\Events\Failed` event for failed sign-ins, with the email only in its
credentials, and signing in and out with tokens fires `Login` and `Logout`, as sessions do. The app's own listeners
of these events see them too, whether the audit is on or not.

To record an authentication event of its own, an app calls `AuditLog::recordAuthEvent()`, which records nothing, when
the setting is off, and adds the IP address and the user agent:

```php
AuditLog::recordAuthEvent('two_factor_failed', $user, ['method' => 'totp']);
```

Its signature is `recordAuthEvent(string $event, ?User $user, array $metadata = [], ?User $causer = null): ?static`;
the user is the subject and, unless a causer is given, the causer.

## Related

- [Audit log](00-overview.md)
- [Auditing models](01-auditing-models.md)
- [Auditing requests](02-auditing-requests.md)
- [Reading and pruning](04-reading-and-pruning.md)
- [Login](../auth/01-login.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Current organization](../organizations/01-current-organization.md)
