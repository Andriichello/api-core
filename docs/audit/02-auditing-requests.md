# Auditing requests

The `audit` middleware (`Core\Http\Middleware\AuditRequests`) records the requests of its routes in the audit log:
who called which route, with what result and, when asked, with which payload. Use it on the API routes, to know who
did what through the API, and to keep the details of failed requests.

## Setup

Put the middleware on the routes to record, the auth routes included:

```php
Route::middleware('audit')->group(function () {
    Route::prefix('auth')->group(fn () => AuthController::routes());

    Route::middleware(['auth:sanctum', 'organization'])->group(function () {
        // the app's routes
    });
});
```

Its parameters choose the scopes of its routes, instead of `api-core.audit.scopes`: `audit:http,data` records the HTTP
side and the payload, `audit:http,data,reads` successful reads too.

## What is recorded

Each recorded request is one entry with:

| Field | Value |
|---|---|
| `source` | `request` |
| `event` | The HTTP method in lower case: `get`, `post`, `put`, `patch` or `delete`. |
| `name` | The route's name, or else its URI, such as `api/invoices/{id}`. |
| `status_code` | The response's status. |
| `causer` | The signed-in user, or none for a guest. |
| `organization_id` | The current organization (set by the `organization` middleware), or else the user's. |
| `metadata.impersonator_id` | The user, who switched to the account, if the request is made with a switched account. |

The entry has no subject. What else `metadata` holds depends on the scopes and on the result.

## Scopes

| Scope | Records |
|---|---|
| `http` | `metadata.http`: the method, the URL with its query, the route's parameters (a bound model as its key), the status, the IP address, the user agent and the duration in milliseconds. |
| `data` | `metadata.payload`: the request's input (query and body), with the `scrub_fields` masked and uploaded files described. |
| `reads` | Successful `GET` requests too. Without it, they aren't recorded at all. |

The default scopes are `['http']`. An entry with both `http` and `data`:

```json
{
    "http": {
        "method": "PATCH",
        "url": "/api/invoices/42",
        "parameters": {"id": "42"},
        "status": 200,
        "ip": "203.0.113.7",
        "user_agent": "Mozilla/5.0 (X11; Linux x86_64)",
        "duration_ms": 84.12
    },
    "payload": {"total": 1500, "status": "sent"}
}
```

## Payloads

- The values of the `scrub_fields` are recorded as `********` (`AuditRequests::MASK`), at any depth and regardless of
  case, so `{"Password": "…", "client": {"refresh_token": "…"}}` is recorded as
  `{"Password": "********", "client": {"refresh_token": "********"}}`.
- An uploaded file is described rather than kept, such as `[file: scan.pdf, 48213 bytes]`.
- A payload larger than `max_payload_size` bytes is recorded as `[too large]`. The size is the request's
  `Content-Length`, so a multipart upload counts its files.

The default `scrub_fields` are `password`, `password_confirmation`, `current_password`, `token`, `access_token`,
`refresh_token`, `remember_token`, `secret`, `client_secret`, `code`, `card_number` and `cvv`. An app, which sets the
list, replaces it as a whole, so it repeats the ones it keeps.

## Errors

A response with a 4xx or 5xx status is always recorded, `GET` included, with `metadata.error`:

- `response`: the response's body, cut to `max_payload_size` characters with `…`;
- `exception`: the class, message, code, file and line of the exception behind it, and its `previous` exceptions. The
  message is the real one, even when a production response hides it;
- `exception.trace` for server errors only, formatted like `getTraceAsString()`, but without the calls' arguments,
  which may hold passwords or personal data.

A request, which an earlier middleware stops, such as one that fails authentication with a 401, is recorded too, with
the scopes, which its route gives the middleware. A request, which matches no route, isn't, since the middleware
belongs to routes.

## Excluded requests

`HEAD` and `OPTIONS` requests are never recorded, nor are those, which `api-core.audit.except` lists:

```php
'audit' => [
    'except' => [
        'paths' => ['api/health', 'api/reports/*'],
        'names' => ['sanctum.csrf-cookie', 'notifications.unread-count'],
    ],
],
```

`paths` are matched with `$request->is()` (no leading slash, `*` as a wildcard) and `names` with `$request->routeIs()`.

## Best-effort recording

The entry is written in the middleware's `terminate()`, after the response has been sent, so recording never slows
down or changes a response. If it fails, such as when the database is unreachable, the exception is reported through
the app's exception handler and the request is not recorded; it is never thrown.

## Related

- [Audit log](00-overview.md)
- [Recording events](03-recording-events.md)
- [Reading and pruning](04-reading-and-pruning.md)
- [Middleware](../http/03-middleware.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Resolving the organization](../organizations/02-resolving-the-organization.md)
