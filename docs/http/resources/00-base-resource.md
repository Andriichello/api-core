# Base resource

`Core\Http\Resources\BaseResource` turns a model into the JSON of a response. Every resource of an application extends
it, so the output starts from the model's visible attributes and adds only the relations (`?include=`) and the
attributes (`?append=`) that the resource allows and the request asks for.

## What it outputs

For a model, `toArray()` starts from `attributesToArray()`: the visible attributes, with the model's casts, its
`$hidden` and `$visible` lists and its own `$appends` applied, and without any relation. Then it adds the requested
includes and appends, in that order.

Starting from the attributes, rather than from `toArray()`, keeps out the relations a model loads for itself, such as
the `roles` that spatie's `hasRole()` loads. A relation reaches the JSON only as an include, which the resource allows
and the request names. An include whose relation isn't loaded is left out rather than loaded lazily (see
[Includes](02-includes.md)).

Anything other than a model (an array, which `asResource()` also takes) is output as it is.

A controller puts the resource under `data` (see [Responses](../01-responses.md)). `GET /api/invoices/7` answers:

```json
{
    "data": {
        "id": 7,
        "organization_id": 1,
        "client_id": 3,
        "number": "INV-0007",
        "status": "draft",
        "created_at": "2026-10-19T21:02:29.000000Z",
        "updated_at": "2026-10-19T21:02:29.000000Z"
    },
    "message": "OK"
}
```

## Writing a resource

A resource lists the includes and the appends that clients may ask for. Both lists are empty by default, so a
`BaseResource` allows none.

```php
namespace App\Http\Resources;

use App\Models\Invoice;
use Core\Http\Resources\BaseResource;

/**
 * Class InvoiceResource.
 *
 * @property Invoice $resource
 */
class InvoiceResource extends BaseResource
{
    protected array $allowedIncludes = [
        'client' => ClientResource::class,
        'lines' => InvoiceLineCollection::class,
    ];

    protected array $allowedAppends = ['balance'];
}
```

To keep a column out of every response, hide it on the model, as `User` hides `password` and `main_account_id`, and
`File` hides its `disk` and `path`. Hidden columns are also left out of the default filters and sorts (see
[Filters](../../querying/00-filters.md)).

To add fields, override `toArray()` and merge them into the parent's array, so the includes and the appends still
work. `UserResource` adds the names of the user's roles this way:

```php
public function toArray(Request $request): array
{
    return array_merge(
        parent::toArray($request),
        ['roles' => $this->resource->getRoleNames()->values()->all()]
    );
}
```

A resource that returns its own array without calling the parent, as `NotificationResource` does, outputs exactly
that array, with no includes and no appends.

## Using it in a controller

A controller names its resource and its collection; the repository names the resource too, so it can check and
eager-load the includes before the query runs (see [Includes](02-includes.md#setup)).

```php
class InvoiceController extends CrudController
{
    protected string $resourceClass = InvoiceResource::class;

    protected string $collectionClass = InvoiceCollection::class;
}
```

`ResourceController::asResource()` and `asResourceResponse()` wrap a model in the controller's resource. They take
optional lists of includes and appends, which replace the ones of the request; `null` (the default) uses the request's,
and `[]` turns them off:

```php
return $this->asResourceResponse($invoice, includes: ['client'], appends: []);
```

The listed includes still have to be allowed and loaded. See [Base controllers](../controllers/00-base-controllers.md).

## Resources api-core ships

| Resource               | Output                                                                                  |
|------------------------|-----------------------------------------------------------------------------------------|
| `UserResource`         | The user's attributes and `roles`, the names of the user's roles.                       |
| `MeResource`           | The signed-in user for `auth/me` (see [Me](../../auth/04-me.md)).                       |
| `FileResource`         | A file without its disk and path; allows the `url` append.                              |
| `NotificationResource` | `id`, `type`, `data`, `read_at` and `created_at` of a database notification.            |

## Slugs

`singularSlug()` and `pluralSlug()` name a resource after its class: `InvoiceResource` gives `invoice` and
`invoices`. `isTarget($request)` is true when the name of the request's route contains either of them.

## Related

- [Collections](01-collections.md)
- [Includes](02-includes.md)
- [Appends](03-appends.md)
- [Responses](../01-responses.md)
- [Base controllers](../controllers/00-base-controllers.md)
- [CRUD controller](../controllers/01-crud-controller.md)
