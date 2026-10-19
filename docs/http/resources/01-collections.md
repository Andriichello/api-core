# Collections

`Core\Http\Resources\BaseCollection` turns a list of models into a JSON list, with each model in the resource that
the collection collects. Use a collection for every list an API returns: the pages of `index`, the to-many relations of
includes and any custom list.

## Writing a collection

A collection names the resource of its items in `$collects`, as `UserCollection` does:

```php
namespace App\Http\Resources;

use Core\Http\Resources\BaseCollection;

class InvoiceCollection extends BaseCollection
{
    public $collects = InvoiceResource::class;
}
```

`BaseCollection` collects `BaseResource` through a `#[Collects(BaseResource::class)]` attribute, which subclasses don't
inherit. So a subclass either sets `$collects` or follows Laravel's naming: `InvoiceCollection` collects
`InvoiceResource` (or `Invoice`) of the same namespace. Its items have to be `BaseResource`s, since the collection
hands each of them the includes and the appends.

`BaseCollection` itself, as a controller's `$collectionClass` or for a to-many include without a resource, outputs
the attributes of each model and nothing else.

`resourceOfItems()` gives the class of the items' resource. It is what nested includes under a collection are checked
against (see [Includes](02-includes.md#nested-includes)).

## Output

A collection outputs a JSON list, each item as its resource outputs it:

```json
{
    "data": [
        {"id": 7, "number": "INV-0007", "status": "draft"},
        {"id": 8, "number": "INV-0008", "status": "paid"}
    ],
    "message": "OK"
}
```

(The items show a few of their attributes.) A paginated list adds `meta` next to `data` (see
[Paginated responses](../../pagination/01-paginated-responses.md)).

## Includes and appends of the items

Unless it is given its own lists, a collection reads the `include` and `append` parameters of the request once and
hands them to every item, rather than having each item parse the request again. Every item gets the same includes
and appends, so `GET /api/invoices?include=client&append=balance` outputs the client and the balance of each invoice
on the page.

`setPassedIncludes()` and `setPassedAppends()` replace the request's lists for all the items; `null` uses the
request's, and `[]` turns them off. The controller's helpers take the same lists:

```php
// Each invoice with its client, and no appends, whatever the request asks for
return $this->asCollectionResponse($invoices, includes: ['client'], appends: []);
```

The relations still have to be allowed by the items' resource and loaded on the models.

## In responses

| Method                                                | Response                                                         |
|-------------------------------------------------------|------------------------------------------------------------------|
| `asCollection($models, $includes, $appends)`          | The models in the controller's `$collectionClass`.               |
| `asCollectionResponse($models, $includes, $appends)`  | `200` with the collection under `data`.                          |
| `asPaginatedResponse($builder, $request)`             | `200` with a page of the query in the collection, and `meta`.    |

All three are methods of `ResourceController`, the base of `CrudController` (see
[Base controllers](../controllers/00-base-controllers.md)).

## Collections api-core ships

`UserCollection`, `FileCollection` and `NotificationCollection` collect `UserResource`, `FileResource` and
`NotificationResource`.

## Related

- [Base resource](00-base-resource.md)
- [Includes](02-includes.md)
- [Appends](03-appends.md)
- [Paginated responses](../../pagination/01-paginated-responses.md)
- [Base controllers](../controllers/00-base-controllers.md)
