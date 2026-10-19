# Responses

`Core\Http\Responses\ApiResponse` is the JSON response api-core's controllers return. It extends Laravel's
`JsonResponse` and always puts a `message` into the body, next to the data, so a client finds a `message` in every
answer, successful or not.

## The envelope

A body is a JSON object with a `message`, which comes last, and usually a `data` key. A single model:

```json
{
    "data": {
        "id": 1,
        "number": "INV-0001"
    },
    "message": "Created"
}
```

A page of models adds `meta` (see [Paginated responses](../pagination/01-paginated-responses.md)). For
`GET /api/invoices?page[size]=2` of three invoices:

```json
{
    "data": [
        {"id": 1, "number": "INV-0001"},
        {"id": 2, "number": "INV-0002"}
    ],
    "meta": {
        "from": 1,
        "to": 2,
        "path": "http://localhost/api/invoices",
        "per_page": 2,
        "current_page": 1,
        "last_page": 2,
        "total": 3,
        "links": {
            "first": "http://localhost/api/invoices?page%5Bsize%5D=2&page%5Bnumber%5D=1",
            "prev": null,
            "self": "http://localhost/api/invoices?page%5Bsize%5D=2&page%5Bnumber%5D=1",
            "next": "http://localhost/api/invoices?page%5Bsize%5D=2&page%5Bnumber%5D=2",
            "last": "http://localhost/api/invoices?page%5Bsize%5D=2&page%5Bnumber%5D=2"
        }
    },
    "message": "OK"
}
```

An answer without data, such as the one of a deletion:

```json
{
    "message": "Deleted"
}
```

The CRUD operations answer with `OK` (index and show), `Created` (store, with status 201), `Updated`, `Deleted` and
`Restored`. Errors aren't `ApiResponse`s, but Laravel renders them with a `message` too (see [Errors](02-errors.md)).

## Making a response

The static constructors take the data and a message, which defaults to the status's text:

| Method | Status | Default message |
|---|---|---|
| `ApiResponse::ok(array $data = [], string $msg = 'OK')` | 200 | `OK` |
| `ApiResponse::created(array $data = [], string $msg = 'Created')` | 201 | `Created` |
| `ApiResponse::noContent(string $msg = 'No Content')` | 204 | `No Content` |
| `ApiResponse::bad(array $data = [], string $msg = 'Bad Request')` | 400 | `Bad Request` |
| `ApiResponse::unauthorized(array $data = [], string $msg = 'Unauthorized')` | 401 | `Unauthorized` |
| `ApiResponse::forbidden(array $data = [], string $msg = 'Forbidden')` | 403 | `Forbidden` |
| `ApiResponse::notFound(array $data = [], string $msg = 'Not Found')` | 404 | `Not Found` |
| `ApiResponse::error(array $data = [], string $msg = 'Internal server error')` | 500 | `Internal server error` |
| `ApiResponse::make(array $data, int $status, string $message)` | any | — |

`make()`, which the others call, adds the message only when the data has no `message` key of its own:
`ApiResponse::ok(['message' => 'Mine', 'data' => 1])` answers `{"message": "Mine", "data": 1}`.

A 204 response has no body over HTTP, so the message of `noContent()` never reaches the client.

Two methods change a response and return it, so they chain:

- `setMessage(string $message)` replaces the message.
- `setStatus(int $code)` changes the status code.

`CrudController` answers a created model with
`$this->asResourceResponse($model)->setMessage('Created')->setStatus(201)`.

## In controllers

Any controller method can return one:

```php
use App\Models\Invoice;
use Core\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

public function summary(Request $request): ApiResponse
{
    return ApiResponse::ok([
        'data' => [
            'count' => Invoice::query()->index($request->user())->count(),
        ],
    ]);
}
```

The data may hold resources too, which are serialized with the request's includes and appends.

Controllers extending `ResourceController` rarely build the envelope themselves: `asResourceResponse()`,
`asCollectionResponse()` and `asPaginatedResponse()` wrap models in the controller's resource or collection and return
an `ApiResponse` (see [Base controllers](controllers/00-base-controllers.md)).

## Related

- [Errors](02-errors.md)
- [Base controllers](controllers/00-base-controllers.md)
- [CRUD controller](controllers/01-crud-controller.md)
- [Base resource](resources/00-base-resource.md)
- [Collections](resources/01-collections.md)
- [Paginated responses](../pagination/01-paginated-responses.md)
