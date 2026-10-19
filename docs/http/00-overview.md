# HTTP layer

api-core's HTTP layer turns a request into a JSON response through a fixed chain of pieces: middleware, a controller,
the operation's request, a policy, a repository and a resource. This page shows how a request flows through that chain
and which of the pieces an application writes for each of its models.

## The flow of a request

Take `PATCH /api/invoices/5` with `{"number": "INV-0005"}`, which an application's `InvoiceController` (a
`CrudController`) handles:

1. **Global middleware.** `ForceJsonOnApi` runs first. The path is under `api/`, so the request is made to expect JSON,
   and every error on the way is rendered as JSON.
2. **Route middleware**, sorted by priority: `auth:sanctum` authenticates the user, `organization` sets the
   organization the request acts in, `locale` sets the locale, then the others, such as `password.updated`, `audit` and
   the route bindings.
3. **Controller.** `CrudController::update()` passes the request to `handle('update', $request)`, which makes the
   operation's request (the `UpdateRequest` subclass listed in `$operations`) from it with `createFrom()`. Its input is
   normalized with `defaults()` and `overrides()`.
4. **Parents.** When the controller nests its routes under parent models (`$parents`), each parent is looked up within
   what the user may list. A parent that isn't found answers 404.
5. **Policy.** `CrudPolicy::determine()` checks the user's permissions for the request's ability (`update`), then looks
   the target model up within what the user may list (404 when it isn't found), and asks the policy's `update()` about
   it: 403 when either check denies.
6. **Validation.** The request's rules run: 422 when the input is invalid.
7. **Handler.** `handleUpdate()` finds the invoice through the repository's query builder (the user's index scope,
   filters, sorts, includes and parents), updates it through the repository, and wraps the fresh model in the
   controller's resource.
8. **Response.** An `ApiResponse` with `{"data": {...}, "message": "Updated"}`.

An exception thrown anywhere on the way is rendered as JSON by Laravel's exception handler (see
[Errors](02-errors.md)).

## What an application writes

For each model that has CRUD routes, an application writes these pieces. Most of them only declare what is specific
to the model; the behavior comes from the base classes.

| Piece | Extends | What it declares | Documentation |
|---|---|---|---|
| Model | `Core\Models\BaseModel` | Columns, casts, relations | [Base model](../models/00-base-model.md) |
| Query | `Core\Queries\BaseQuery` | `index($user)`: what a user may list | [Index scopes](../queries/01-index-scopes.md) |
| Repository | `Core\Repositories\CrudRepository` | The model, allowed filters, sorts and includes | [CRUD repository](../repositories/01-crud-repository.md) |
| Policy | `Core\Policies\CrudPolicy` | The model, checks beyond the permissions | [CRUD policy](policies/01-crud-policy.md) |
| Requests | `StoreRequest`, `UpdateRequest` | The rules of the fields | [CRUD requests](requests/01-crud-requests.md) |
| Resources | `BaseResource`, `BaseCollection` | The JSON of a model, allowed includes and appends | [Base resource](resources/00-base-resource.md) |
| Controller | `Core\Http\Controllers\CrudController` | The policy, repository, resources and operations | [CRUD controller](controllers/01-crud-controller.md) |
| Routes | — | The paths and their middleware, in `routes/api.php` | [Middleware](03-middleware.md) |

[Building a feature](../getting-started/06-building-a-feature.md) walks through all of them for an `Invoice`.

api-core also ships controllers with their own routes: `AuthController::routes()`, `UserController::routes()` and
`NotificationController::routes()`, and two abstract controllers, which an application extends once per model:
`FileController` for a model's files and `SettingController` for its settings.

## Endpoints that aren't CRUD

An endpoint that doesn't fit the CRUD operations can still use the same pieces:

- an extra operation of a `CrudController`, with its own request and handler
  (see [Custom operations](controllers/04-custom-operations.md));
- a controller extending `ResourceController`, which only wraps models in resources and responses
  (see [Base controllers](controllers/00-base-controllers.md));
- any controller method returning an `ApiResponse` (see [Responses](01-responses.md)).

## Namespaces

| Namespace | Contents |
|---|---|
| `Core\Http\Controllers` | `BaseController`, `ResourceController`, `CrudController`, the shipped controllers |
| `Core\Http\Controllers\Traits` | `NestsUnderParent`, `WithPagination` |
| `Core\Http\Requests` | `BaseRequest`, `CrudRequest`, `Crud\*` (one per operation), `Traits\*`, `Interfaces\*` |
| `Core\Http\Resources` | `BaseResource`, `BaseCollection`, `ResourcePaginator`, the shipped resources |
| `Core\Http\Responses` | `ApiResponse` |
| `Core\Http\Middleware` | The middleware api-core registers |
| `Core\Http\Filters` | The filters of the `filter` query parameter |

## Related

- [Responses](01-responses.md)
- [Errors](02-errors.md)
- [Middleware](03-middleware.md)
- [CRUD controller](controllers/01-crud-controller.md)
- [Base request](requests/00-base-request.md)
- [Base resource](resources/00-base-resource.md)
- [Base policy](policies/00-base-policy.md)
- [Overview of api-core](../getting-started/00-overview.md)
- [Building a feature](../getting-started/06-building-a-feature.md)
