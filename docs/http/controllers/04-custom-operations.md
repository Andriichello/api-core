# Custom operations

A CRUD controller can handle operations beyond the standard five, such as sending an invoice, and can change how any
operation is handled. An extra operation gets the same pipeline as the others: its own request, the parents, the
policy, validation and a handler.

## Adding an operation

An operation needs a request, which names the ability the policy checks, a handler and a route. To send an invoice:

```php
namespace App\Http\Requests\Invoices;

use Core\Http\Requests\Crud\ShowRequest;

class SendInvoiceRequest extends ShowRequest
{
    protected ?string $ability = 'send';
}
```

`ShowRequest` brings the target (`{id}`), so the policy gets the invoice. The controller lists the operation and
handles it in `handle{Operation}()`:

```php
protected array $operations = [
    // index, show, store, update, destroy
    'send' => SendInvoiceRequest::class,
];

protected function handleSend(SendInvoiceRequest $request): ApiResponse
{
    $invoice = $this->builder($request)->findOrFail($request->getId());
    $invoice->update(['sent_at' => now()]);

    return $this->asResourceResponse($invoice)->setMessage('Sent');
}
```

The policy has a method of the ability's name, and the roles that may send list `send` among their actions on
`Invoice::class` in `api-core.roles` (until the permission exists, only super admins pass):

```php
class InvoicePolicy extends CrudPolicy
{
    protected BaseModel|string $model = Invoice::class;

    public function send(mixed $user, mixed $model): bool
    {
        return $this->hasAnyRole($user) && $model->sent_at === null;
    }
}
```

## Routes of extra operations

There is no `send()` method on the controller: `__call()` passes the call to `handle()`. Register the route inside a
group, which gives it its attributes:

```php
Route::middleware(['auth:sanctum', 'organization'])->group(function () {
    Route::post('invoices/{id}/send', [InvoiceController::class, 'send'])->name('invoices.send');
});
```

Attributes chained before such a route (`Route::middleware(...)->post(...)`) apply to it on Laravel 13.37, but earlier
13.x releases, which `^13.0` allows, don't see `__call()` as the operation's method and silently drop them. On any
release, they apply to a route in a group, to one with a `Controller@operation` action, or to an operation with a
method of its own:

```php
Route::middleware('auth:sanctum')->post('invoices/{id}/send', InvoiceController::class . '@send');
```

```php
public function send(Request $request): ApiResponse
{
    return $this->handle('send', $request);
}
```

`UserController` gives `resendInvitation` such a method. A name that isn't in `$operations`, or an operation without
its handler, throws a `BadMethodCallException` (500).

## The ability of a request

The policy's `determine()` reads the request's `$ability`:

- an ability of the CRUD operations (`viewAny`, `view`, `create`, `update`, `delete`, `forceDelete`, `restore`) works
  with `CrudPolicy`'s own methods and permissions;
- any other ability needs the policy's method of that name (without one, `BasePolicy` denies it) and the permission of
  that action;
- a request with a target (`WithTargetInterface`) is checked against its target, found within the user's index scope;
- a `CrudRequest` without an ability isn't checked by the policy at all.

An operation that only reads a list, such as an export, can take `IndexRequest` itself, which checks `viewAny`.

## Overriding a handler

Each standard operation has a protected handler, which a controller overrides: `handleIndex()`, `handleShow()`,
`handleStore()`, `handleUpdate()`, `handleDestroy()` and `handleRestore()`. To create an invoice with its lines, which
the request validates under `lines`:

```php
protected function handleStore(StoreRequest $request): ApiResponse
{
    $invoice = DB::transaction(function () use ($request) {
        $invoice = $this->repository->create(
            Arr::except($request->validated(), 'lines'),
            $request->getParents()
        );

        $invoice->lines()->createMany($request->validated('lines', []));

        return $invoice;
    });

    return $this->asResourceResponse($invoice)->setMessage('Created')->setStatus(201);
}
```

`builder()` (the query of every operation but store), `checkPolicy()` and `prepareRequest()` can be overridden too.

## Responses that aren't JSON

`handle()` returns an `ApiResponse`. An operation that answers with a file or a stream gets a public method, which
prepares the request (resolving the parents, authorizing and validating it) and builds the response itself, as
`FileController::download()` does. A CSV export of the invoices the user may list, filtered and sorted as the index:

```php
public function export(Request $request): StreamedResponse
{
    /** @var IndexRequest $indexRequest */
    $indexRequest = $this->prepareRequest('export', $request);
    $invoices = $this->builder($indexRequest)->cursor();

    return response()->streamDownload(function () use ($invoices) {
        $output = fopen('php://output', 'w');

        foreach ($invoices as $invoice) {
            fputcsv($output, [$invoice->id, $invoice->number]);
        }

        fclose($output);
    }, 'invoices.csv', ['Content-Type' => 'text/csv']);
}
```

The operation is listed in `$operations` as usual (`'export' => IndexRequest::class`), and needs no handler.

## Related

- [CRUD controller](01-crud-controller.md)
- [CRUD requests](../requests/01-crud-requests.md)
- [Targets](../requests/02-targets.md)
- [CRUD policy](../policies/01-crud-policy.md)
- [Base policy](../policies/00-base-policy.md)
- [Configuring roles](../../roles/01-configuring-roles.md)
- [Managing users](../../users/01-managing-users.md)
- [File routes](../../files/02-file-routes.md)
