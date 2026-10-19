# Resolving the organization

The `organization` middleware (`Core\Http\Middleware\ResolveOrganization`) sets the
[current organization](01-current-organization.md) of a request: the signed-in user's own one or, for a super admin,
the one the request names. Put it on every route that works with the records of an organization.

## Setup

api-core registers the middleware under the alias `organization` (`ResolveOrganization::ALIAS`). Put it after
authentication, since it reads the signed-in user:

```php
// routes/api.php
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);
});
```

`UserController::routes()` adds both middleware to its routes itself.

api-core ranks the middleware right after authentication in Laravel's middleware priority, so it runs before
`SubstituteBindings`, even though the `api` group lists the bindings first. Route model bindings therefore find the
models of the request's organization, and a model of another organization is not found (`404`).

## How the organization is chosen

The request may name an organization by its id in the `X-Organization-Id` header (`ResolveOrganization::HEADER`) or,
for links that can't send headers, such as downloads, in the `x_organization_id` query parameter
(`ResolveOrganization::QUERY`). Then:

1. Without either of them, the request acts in the user's own organization, or in none for a user without one.
2. Naming the user's own organization changes nothing, so every user may send the header.
3. Naming another organization is allowed only for super admins (the `super-admin` role), and the organization
   must exist; a soft-deleted one is not found.

When both the header and the query parameter are given, they must be equal.

## How a super admin picks an organization

A super admin without an organization acts in all organizations: the lists of users and organizations show all of
them (see [Acting organization](04-acting-organization.md)). Organization-scoped models, however, find nothing until
an organization is current, since their scope fails closed (see
[Organization-scoped models](03-organization-scoped-models.md)). So a super admin's client names the organization
on each request:

```http
GET /api/invoices HTTP/1.1
Authorization: Bearer 12|e8Rk...
X-Organization-Id: 7
```

A link, such as a file download, names it in the query:

```http
GET /api/invoices/15/files/3/download?x_organization_id=7 HTTP/1.1
```

The request then acts in organization `7`: scoped models see its records, new ones are created in it, and the lists
of users and organizations narrow to it. A super admin with an organization of their own acts in it by default, and
may name another one in the same way.

## Errors

| Status | Message                                                     | When                                                  |
|--------|-------------------------------------------------------------|-------------------------------------------------------|
| `400`  | `The organization must be named by its id.`                 | The value isn't a non-negative integer, e.g. `acme`.  |
| `400`  | `X-Organization-Id and x_organization_id must match.`       | Both are given with different values.                 |
| `400`  | `x_organization_id must be an id of an organization.`       | The query parameter is an array (`x_organization_id[]=1`). |
| `403`  | `Only super admins may act in another organization.`        | A user, who isn't a super admin, names another organization. |
| `404`  | `The organization is not found.`                            | A super admin names an organization that doesn't exist or is deleted. |

```json
{
    "message": "Only super admins may act in another organization."
}
```

## In your code

Read the result with `CurrentOrganization::id()`, or with `$request->orgId()` in a request that extends
`Core\Http\Requests\BaseRequest`, which falls back to the user's own organization on routes without the middleware.
Never take the organization from the request's input yourself: the middleware is what checks that the user may act
in it.

```php
use Core\Helpers\CurrentOrganization;

$organizationId = CurrentOrganization::id();
```

## Related

- [Current organization](01-current-organization.md)
- [Organization-scoped models](03-organization-scoped-models.md)
- [Acting organization](04-acting-organization.md)
- [Middleware](../http/03-middleware.md)
- [Base request](../http/requests/00-base-request.md)
- [Downloads and URLs](../files/04-downloads-and-urls.md)
- [Switching accounts](../auth/05-switching-accounts.md)
