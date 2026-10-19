# Errors

Errors on API routes are JSON objects with a `message`, plus the `errors` of each field for validation errors. This page
explains how api-core makes them JSON, which errors its pieces answer with, and how missing models are kept from
leaking class names and ids.

## JSON on API paths

`Core\Http\Middleware\ForceJsonOnApi` gives every request whose path matches `api-core.http.json_paths` the header
`Accept: application/json`, even when the client asked for HTML. Laravel's exception handler then renders its errors as
JSON. Without it, an unauthenticated request would be redirected to a login page, an invalid one redirected back, and
other errors rendered as HTML pages.

`CoreServiceProvider` prepends it to the global middleware, so it runs before every other one, and the errors of the
other global middleware, such as 503 in maintenance mode or 413 for a body that is too large, are JSON too. An
application doesn't register it.

| Key | Env | Default | Description |
|---|---|---|---|
| `api-core.http.json_paths` | — | `['api', 'api/*']` | Path patterns (as `Request::is()` takes them) of the API. An empty array turns it off. |

The patterns match `/api` and everything under `/api/`, but not `/apix` or `/docs/api`. The value is a list, so an
application's list replaces api-core's as a whole. For an API under `v1/`, in the application's `config/api-core.php`:

```php
'http' => [
    'json_paths' => ['v1', 'v1/*'],
],
```

## The shape of an error

Laravel renders an exception as `{"message": "..."}`. Validation errors add `errors`, keyed by the field:

```http
POST /api/auth/login
Content-Type: application/json

{"email": "not-an-email"}
```

```json
{
    "message": "The email field must be a valid email address. (and 1 more error)",
    "errors": {
        "email": ["The email field must be a valid email address."],
        "password": ["The password field is required."]
    }
}
```

In debug mode (`APP_DEBUG=true`), errors also carry `exception`, `file`, `line` and `trace`. Outside of it, the message
of an HTTP exception (403, 404, 409, ...) is shown as it was thrown, while any other exception answers 500 with
`{"message": "Server Error"}`. Keep debug mode off in production.

## Plain 404s

Laravel puts the class and id of a missing model into the message of its 404 ("No query results for model
[App\Models\Invoice] 5"), even outside of debug mode. Outside of debug mode, `CoreServiceProvider` maps
`ModelNotFoundException` and `BackedEnumCaseNotFoundException` (an enum route parameter that matches no case) to a plain
404:

```json
{
    "message": "Not found."
}
```

In debug mode, the original message is kept. Since api-core looks targets and parents up within what the user may list,
a model of another organization answers the same 404 as one that doesn't exist, rather than a 403 that would confirm it
exists.

## Errors api-core answers with

| Status | Message | When |
|---|---|---|
| 400 | `The organization must be named by its id.` and others | A bad `X-Organization-Id` or `x_organization_id` |
| 401 | `Unauthenticated.` | No valid token or session, a suspended user, a session ended by a password change |
| 403 | `This action is unauthorized.` | The policy denies the operation (or a message the policy gives) |
| 403 | `Password update is required to use your account.` | The `password.updated` middleware |
| 403 | `Only super admins may act in another organization.` | A user names another organization |
| 403 | The link's message, e.g. `The verification link is invalid or has expired.` | An emailed link that is invalid |
| 404 | `Not found.` | A missing model or parent, or one out of the user's scope |
| 404 | `The route api/... could not be found.` | No route matches the path |
| 409 | `The record is locked, so it can't be deleted.` and others | A locked record changes (`RecordLockedException`) |
| 409 | `The status can't change from paid to draft.` | A status transition that isn't allowed (`StatusTransitionException`) |
| 409 | `The invitation has been accepted already.` | Resending an accepted invitation |
| 422 | Validation messages | Invalid input, filters, sorts, includes or appends |
| 429 | `Too Many Attempts.` | The auth routes' rate limit (`api-core.auth.rate_limit`) |
| 500 | `Server Error` | Any other exception, outside of debug mode |

The 409 exceptions extend Symfony's `ConflictHttpException`, so Laravel doesn't report them.

## Validation error keys

Besides the fields of a request's rules, api-core's validation errors use these keys:

| Key | When |
|---|---|
| `filter`, `filter.{field}` | The `filter` parameter is malformed, a filter isn't allowed, or its value is invalid |
| `sort` | Sorting by a field that isn't allowed |
| `include` | An include that isn't allowed |
| `append` | An append that isn't allowed |
| `body` | A JSON body of a store or update request that isn't an object |
| `fields` | An update that sends none of the fields it can update |
| `settings` | A settings update without settings |

## Related

- [Middleware](03-middleware.md)
- [Responses](01-responses.md)
- [CRUD controller](controllers/01-crud-controller.md)
- [CRUD requests](requests/01-crud-requests.md)
- [Resolving the organization](../organizations/02-resolving-the-organization.md)
- [Required password update](../auth/10-required-password-update.md)
- [Locked records](../models/02-locked-records.md)
- [Status transitions](../models/03-status-transitions.md)
- [Configuration](../getting-started/03-configuration.md)
