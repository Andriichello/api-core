# File routes

`FileController` gives the files of a model routes, which nest them under the model, such as
`invoices/{invoice}/files`: it lists, uploads, shows, downloads, renames and deletes them. An app makes one subclass per
model, whose files have routes.

## Setup

The subclass names the model's class and route parameter, and takes the model's policy, which authorizes every request:

```php
namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use Core\Http\Controllers\FileController;
use Core\Repositories\FileRepository;

class InvoiceFileController extends FileController
{
    protected string $fileable = Invoice::class;

    protected string $parameter = 'invoice';

    public function __construct(InvoicePolicy $policy, FileRepository $repository)
    {
        parent::__construct($policy, $repository);
    }
}
```

Declare the constructor with the model's policy (a `CrudPolicy` subclass, whose `$model` is the fileable): without it,
the container passes a plain `CrudPolicy`, which has no model, and every request fails with a `LogicException`
(`… needs the policy of App\Models\Invoice, Core\Policies\CrudPolicy given.`), rather than run without the model's
permissions. A policy of another model fails the same way. `$fileable` must implement `HasFilesInterface`, and
`$parameter` must be the name of the route parameter, or the request fails with a `LogicException` too.

Register the routes in `routes/api.php`, under the app's auth middleware:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    InvoiceFileController::routes('invoices/{invoice}/files');
});
```

## Routes

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/invoices/{invoice}/files` | A page of the invoice's files. |
| `POST` | `/api/invoices/{invoice}/files` | Upload a file (`file`, and an optional `name`). |
| `GET` | `/api/invoices/{invoice}/files/{id}` | One file. |
| `GET` | `/api/invoices/{invoice}/files/{id}/download` | The file's contents (see [Downloads](04-downloads-and-urls.md)). |
| `PATCH` | `/api/invoices/{invoice}/files/{id}` | Rename the file (`name`). |
| `DELETE` | `/api/invoices/{invoice}/files/{id}` | Delete the file and its stored contents. |

`routes()` registers only the routes of the operations in `$operations`, so a read-only controller, which leaves out
`store`, `update` and `destroy` (as in [Collections](#collections)), has no `POST`, `PATCH` or `DELETE` route.

## Access

With the `NestsUnderParent` trait, the controller handles each request in three steps (see
[Nesting under a parent](../http/controllers/05-nesting-under-a-parent.md)):

1. It finds the invoice by its route parameter within the user's index scope (`Invoice::query()->index($user)`). An
   invoice the user can't list is a 404, whatever the request.
2. It authorizes the request as an ability on the invoice, with the invoice's policy, its `before()` and permissions
   included. A denial is a 403.
3. It validates the request (a 422), and the operation finds the file among the invoice's files only, so a file of
   another invoice is a 404.

| Operations | Ability on the fileable |
|---|---|
| list, show, download | `view` |
| upload, rename, delete | `update` |

There are no permissions of their own for files: a role, which may update invoices, may change their files. To map the
abilities differently, override `parentAbilities()`, which returns the whole map. It maps the requests' abilities
(`viewAny`, `view`, `create`, `update`, `delete`, `forceDelete`) to abilities on the fileable, and a request, whose
ability it doesn't list, is denied.

## Collections

`$collection` limits a controller to one collection: it lists and finds only its files and uploads into it. Without
it, the routes see the files of every collection and upload into `default`. Two controllers can expose two collections
of the same model, each at its own URI, such as `invoices/{invoice}/generated-files`:

```php
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;

class GeneratedInvoiceFileController extends InvoiceFileController
{
    protected ?string $collection = 'generated';

    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'download' => ShowRequest::class,
    ];
}
```

## Deeper nesting

When the fileable itself is nested, list the outer route parameters in `$parents`. They are the fileable's `BelongsTo`
relations, and the fileable is found only within them:

```php
protected array $parents = ['client'];

// InvoiceFileController::routes('clients/{client}/invoices/{invoice}/files');
```

## Lists

The list is paginated, filtered and sorted like other CRUD lists. Besides the default filters and sorts (the key, the
fillable columns and the timestamps), it may be filtered by `collection`, `mime_type` and `size`, and sorted by `size`.
`disk` and `path` can be neither. Without a `sort`, files are listed by `id`, ascending. `?append=url` adds each
file's temporary URL.

```http
GET /api/invoices/42/files?filter[mime_type][eq]=application/pdf&sort=-size
Authorization: Bearer {access-token}
```

## Responses

An upload is a `multipart/form-data` request with the `file` and an optional `name` (see
[Upload validation](03-upload-validation.md#uploading)). It answers `201` with the file:

```json
{
    "data": {
        "name": "Act of October.pdf",
        "metadata": null,
        "fileable_id": 42,
        "fileable_type": "models.invoices",
        "collection": "default",
        "mime_type": "application/pdf",
        "size": 48213,
        "sha256": "50d858e0985ecc7f60418aaf0cc5ab587f42c2570a884095a9e8ccacd0f6545c",
        "updated_at": "2026-10-20T09:15:02.000000Z",
        "created_at": "2026-10-20T09:15:02.000000Z",
        "id": 7
    },
    "message": "Created"
}
```

`GET` of a file answers the same fields, in the order of the table's columns, with `"message": "OK"`, and `PATCH`
with `"message": "Updated"`. The list answers the files of a page in `data`, with `meta` (see
[Paginated responses](../pagination/01-paginated-responses.md)). `DELETE` answers `{"message": "Deleted"}`.

## Errors

| Status | When |
|---|---|
| 401 | The request isn't authenticated. |
| 403 | The policy denies the ability on the fileable. |
| 404 | The fileable isn't in the user's index scope, the file isn't one of its files (or of the controller's collection), or the stored file of a download is missing. |
| 405 | A method, whose operation the controller doesn't handle, on a URI it has other routes of, such as a `POST` to a read-only list. |
| 422 | The upload or the new name is invalid (see [Upload validation](03-upload-validation.md)). |

## Related

- [Files](00-overview.md)
- [Attaching files](01-attaching-files.md)
- [Upload validation](03-upload-validation.md)
- [Downloads and URLs](04-downloads-and-urls.md)
- [Nesting under a parent](../http/controllers/05-nesting-under-a-parent.md)
- [CRUD controller](../http/controllers/01-crud-controller.md)
- [CRUD policy](../http/policies/01-crud-policy.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Filters](../querying/00-filters.md)
- [Sorting](../querying/04-sorting.md)
- [Paginating lists](../pagination/00-paginating-lists.md)
