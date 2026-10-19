# Building a feature: the endpoints

The second half of the `Invoice` example: its requests, resource, controller and routes, and what calling them
returns. It builds on the model, query, repository, policy and permissions of
[Building a feature](06-building-a-feature.md).

## Requests

```php
namespace App\Http\Requests\Invoices;

use Core\Http\Requests\Crud\StoreRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends StoreRequest
{
    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('organization_id', $this->orgId()),
            ],
            'number' => ['required', 'string', 'max:32'],
            'total' => ['required', 'numeric', 'min:0'],
            'due_on' => ['nullable', 'date'],
        ];
    }
}
```

```php
namespace App\Http\Requests\Invoices;

use Core\Http\Requests\Crud\UpdateRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends UpdateRequest
{
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(['draft', 'sent', 'paid'])],
            'total' => ['sometimes', 'numeric', 'min:0'],
            'due_on' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
```

Only validated fields reach the repository. `orgId()` is the organization, in which the request acts, never one taken
from the input, so a client of another organization fails `exists`. An update without any field of the rules is a 422
on `fields`.

## Resource and collection

```php
namespace App\Http\Resources;

use Core\Http\Resources\BaseResource;

class InvoiceResource extends BaseResource
{
    protected array $allowedIncludes = ['client'];
}
```

```php
namespace App\Http\Resources;

use Core\Http\Resources\BaseCollection;

class InvoiceCollection extends BaseCollection
{
    public $collects = InvoiceResource::class;
}
```

A resource outputs the model's attributes, without its hidden ones. `client` is included as it is; map it to a
`ClientResource` (`['client' => ClientResource::class]`) to allow includes nested under it.

## Controller

```php
namespace App\Http\Controllers;

use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceCollection;
use App\Http\Resources\InvoiceResource;
use App\Policies\InvoicePolicy;
use App\Repositories\InvoiceRepository;
use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\DestroyRequest;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\RestoreRequest;
use Core\Http\Requests\Crud\ShowRequest;

class InvoiceController extends CrudController
{
    protected string $resourceClass = InvoiceResource::class;

    protected string $collectionClass = InvoiceCollection::class;

    protected array $operations = [
        'index' => IndexRequest::class,
        'show' => ShowRequest::class,
        'store' => StoreInvoiceRequest::class,
        'update' => UpdateInvoiceRequest::class,
        'destroy' => DestroyRequest::class,
        'restore' => RestoreRequest::class,
    ];

    public function __construct(InvoicePolicy $policy, InvoiceRepository $repository)
    {
        parent::__construct($policy, $repository);
    }
}
```

The constructor names the concrete policy and repository, so the container can build the controller. The controller
calls the policy itself, so the policy needs no registration with `Gate`.

## Routes

In `routes/api.php`:

```php
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'password.updated', 'organization'])->group(function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::patch('invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('invoices/{id}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore');
});
```

The record's route parameter is named `{id}`. `organization` sets the organization, to which `BelongsToOrganization`
limits the invoices.

## Calling it

```http
POST /api/invoices
Authorization: Bearer 12|kZ0…
Content-Type: application/json

{"client_id": 1, "number": "INV-0001", "total": 120, "due_on": "2026-11-30"}
```

```json
{
    "data": {
        "status": "draft",
        "client_id": 1,
        "number": "INV-0001",
        "total": "120.00",
        "due_on": "2026-11-30T00:00:00.000000Z",
        "organization_id": 1,
        "updated_at": "2026-10-19T21:04:47.000000Z",
        "created_at": "2026-10-19T21:04:47.000000Z",
        "id": 1
    },
    "message": "Created"
}
```

The status is `201`. A list is a page of the collection, with its `meta` (see
[Paginated responses](../pagination/01-paginated-responses.md)), and `show`, `update` and `restore` return the
invoice as it is stored, with all of its columns.

| Request                                                                  | Answer                                              |
|--------------------------------------------------------------------------|-----------------------------------------------------|
| `GET /api/invoices?filter[status][eq]=draft&sort=-total&include=client`  | 200, a page of invoices, each with its `client`     |
| `GET /api/invoices?include=organization`                                 | 422 on `include`, which isn't allowed               |
| `GET /api/invoices/1` by a user of another organization                  | 404 `Not found.`                                    |
| `POST /api/invoices` with a client of another organization               | 422 on `client_id`                                  |
| `POST /api/invoices` by a user with the `user` role                      | 403 `This action is unauthorized.`                  |
| `PATCH /api/invoices/1` of a paid invoice, even with an empty body       | 403, as the policy runs before validation           |
| `DELETE /api/invoices/1`                                                 | 200 `{"message": "Deleted"}`                        |
| `POST /api/invoices/1/restore` of the deleted invoice                    | 200, the invoice with the message `Restored`        |

## Related

- [Building a feature](06-building-a-feature.md)
- [CrudController](../http/controllers/01-crud-controller.md)
- [CRUD requests](../http/requests/01-crud-requests.md)
- [Base resource](../http/resources/00-base-resource.md)
- [Includes](../http/resources/02-includes.md)
- [Restoring](../http/controllers/03-restoring.md)
- [Responses](../http/01-responses.md)
- [Errors](../http/02-errors.md)
