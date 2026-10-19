# Middleware

api-core registers six middleware. Two of them work on their own; the other four have aliases, which an application
puts on its routes. This page lists each one, the order they run in, and where to use them; the features they serve are
explained in the linked documents.

## The middleware

| Class (`Core\Http\Middleware\…`) | Alias | Registered by | Where it goes |
|---|---|---|---|
| `ForceJsonOnApi` | — | `CoreServiceProvider`, as the first global middleware | Nowhere: it runs on every request |
| `AuthenticateSession` | — | `AuthServiceProvider`, in place of Sanctum's `AuthenticateSession` | Nowhere: Sanctum runs it for the SPA's requests |
| `ResolveOrganization` | `organization` | `CoreServiceProvider` | With `auth:sanctum`, on routes that act in an organization |
| `SetLocale` | `locale` | `CoreServiceProvider` | On the API routes |
| `RequireUpdatedPassword` | `password.updated` | `AuthServiceProvider` | With `auth:sanctum`, on the app's routes, but not the auth routes |
| `AuditRequests` | `audit` | `CoreServiceProvider` | On the API routes, the auth routes included |

- **`ForceJsonOnApi`** makes requests to the API paths (`api-core.http.json_paths`) expect JSON, so their errors are
  JSON. See [Errors](02-errors.md).
- **`AuthenticateSession`** signs a session out when the user's password changes or the user can no longer sign in, and
  ends a switched session when the user who switched can't. Sanctum's stateful middleware (`$middleware->statefulApi()`)
  runs the class in `sanctum.middleware.authenticate_session`, which is Sanctum's `AuthenticateSession` by default; the
  container resolves it to api-core's. See [Sessions](../auth/03-sessions.md).
- **`organization`** sets the organization the request acts in: the user's own, or the one a super admin names in
  `X-Organization-Id` or `x_organization_id`. See
  [Resolving the organization](../organizations/02-resolving-the-organization.md).
- **`locale`** sets the request's locale from the user, `Accept-Language` or `app.locale`, among `api-core.locales`, and
  names it in `Content-Language`. See [Locale middleware](../localization/00-locale-middleware.md).
- **`password.updated`** answers 403 until a person whose password was set by someone else sets a new one. See
  [Required password update](../auth/10-required-password-update.md).
- **`audit`** records the requests in the audit log after their responses are sent. Its parameters name the scopes,
  e.g. `audit:http,data,reads`. See [Auditing requests](../audit/02-auditing-requests.md).

## Order

Laravel sorts a route's middleware by its middleware priority list, and keeps the order of the ones that aren't in it.
api-core adds three of its middleware to that list:

1. `ResolveOrganization` right after authentication (`AuthenticatesRequests`, which the `auth` middleware of
   `auth:sanctum` implements), so it knows the user, and route model bindings (`SubstituteBindings`, which comes later
   in the list) find the models of the request's organization, although the `api` group lists the bindings first.
2. `SetLocale` right after `ResolveOrganization`, so it knows the user's locale, however the route orders them.
3. `RequireUpdatedPassword` right after `SetLocale`, so it finds the user of a token: before authentication,
   `$request->user()` asks the default guard, which doesn't know the users of tokens, and would let the request through.

So a route with `['password.updated', 'locale', 'auth:sanctum', 'organization']` runs authentication, then
`organization`, then `locale`, then `password.updated`. An outer group may list `password.updated` around routes,
which add `auth:sanctum` themselves:

```php
Route::middleware('password.updated')->group(function () {
    UserController::routes(); // its routes add auth:sanctum, which runs first
});
```

`AuditRequests` has no priority, so it runs where the route lists it, and a group's middleware run before the ones of
the routes inside it.

`audit` records the request after the response, whatever its position, and records requests that an earlier
middleware stops, such as unauthenticated ones, too.

## Routes of an application

A `routes/api.php`, which uses all of them:

```php
use App\Http\Controllers\InvoiceController;
use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Controllers\NotificationController;
use Core\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['locale', 'audit'])->group(function () {
    Route::prefix('auth')->group(fn () => AuthController::routes());

    Route::middleware(['auth:sanctum', 'password.updated', 'organization'])->group(function () {
        UserController::routes();
        NotificationController::routes();

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::patch('invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });
});
```

`AuthController::routes()` puts `auth:sanctum` and the rate limit on its own routes, and `UserController::routes()`
puts `auth:sanctum` and `organization` on its own; Laravel drops a middleware that a route gets twice.

## Related

- [Errors](02-errors.md)
- [Overview of the HTTP layer](00-overview.md)
- [Sessions](../auth/03-sessions.md)
- [Required password update](../auth/10-required-password-update.md)
- [Resolving the organization](../organizations/02-resolving-the-organization.md)
- [Locale middleware](../localization/00-locale-middleware.md)
- [Auditing requests](../audit/02-auditing-requests.md)
- [Auth overview](../auth/00-overview.md)
- [Setting up a SPA](../getting-started/04-spa.md)
