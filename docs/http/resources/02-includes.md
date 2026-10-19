# Includes

The `include` query parameter adds related records to a response, such as the client of an invoice, so a client
application gets them in one request. A resource lists the relations that may be included, and the repository loads
them, limited to the records the user may list.

## Allowing includes

A resource lists its includes in `$allowedIncludes`, by the names of the model's relations. An entry either names the
relation alone or maps it to the resource it is output with:

```php
class InvoiceResource extends BaseResource
{
    protected array $allowedIncludes = [
        'client' => ClientResource::class,
        'lines' => InvoiceLineCollection::class,
        'tags',
    ];
}
```

| Entry                                         | Output as                                             | Includes nested under it          |
|-----------------------------------------------|-------------------------------------------------------|-----------------------------------|
| `'client' => ClientResource::class`           | `ClientResource`                                      | The ones `ClientResource` allows. |
| `'lines' => InvoiceLineCollection::class`     | `InvoiceLineCollection`                               | The ones its items' resource allows. |
| `'tags'`                                      | `BaseResource`, or `BaseCollection` for a to-many one | None.                             |

Map a to-one relation to a resource and a to-many relation to a collection. A relation without a resource outputs the
attributes of its models only, like any `BaseResource`.

## Setup

The repository checks the includes against a resource and eager-loads them while it builds the query, so it names the
same resource as the controller:

```php
class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected string $resourceClass = InvoiceResource::class;
}
```

Without it, the repository loads nothing, and since a resource never loads a relation lazily, the allowed includes are
left out of the responses. An include that isn't allowed still answers `422`, once the response is built.

## Requesting includes

`include` is a comma-separated list. A dot nests an include under another one, and every step of the path is included
too: `lines.product` includes `lines` and the `product` of each line.

```http
GET /api/invoices/7?include=client,lines.product
```

```json
{
    "data": {
        "id": 7,
        "client_id": 3,
        "number": "INV-0007",
        "client": {"id": 3, "name": "Globex"},
        "lines": [
            {"id": 21, "product_id": 9, "quantity": 2, "product": {"id": 9, "name": "Consulting hour"}}
        ]
    },
    "message": "OK"
}
```

(The records show a few of their attributes.) A to-one relation without a record is `null`, and a to-many one without
records is `[]`. Includes are loaded for `index` and `show`. The `store`, `update` and `restore` operations answer with
the model as it is saved, without relations, so the includes are left out of their responses.

## Nested includes

A nested include is allowed only by the resource that its relation is output with, or by the resource of the items of
its collection. `client.contacts` is allowed when `ClientResource` allows `contacts`, and nothing can be nested under
`tags`, which has no resource. A resource therefore controls everything that can be reached through it, wherever it is
included from.

## Scoping of included records

The repository constrains the eager load of every include with the `index($user)` of the related model's query, so an
include reaches no more than listing the related model would:

- a to-one relation to a record the user can't list is `null`;
- a to-many relation leaves out the records the user can't list;
- a `morphTo` relation scopes the query of each type of the related models.

A related model whose query isn't a `BaseQuery` (such as spatie's `Role`) is loaded as it is. See
[Eager loads](../../repositories/02-eager-loads.md) and [Index scopes](../../queries/01-index-scopes.md).

## Errors

An include that isn't allowed answers `422` with an `include` error, which names the includes allowed where the path
stops being allowed:

```http
GET /api/invoices?include=client.owner
```

```json
{
    "message": "The 'client.owner' include is not allowed. Allowed includes: client.contacts.",
    "errors": {
        "include": [
            "The 'client.owner' include is not allowed. Allowed includes: client.contacts."
        ]
    }
}
```

| Request                       | Message                                                                                 |
|-------------------------------|-----------------------------------------------------------------------------------------|
| `include=payments`            | `The 'payments' include is not allowed. Allowed includes: client, lines, tags.`         |
| `include=tags.owner`          | `The 'tags.owner' include is not allowed. No includes are allowed under 'tags'.`        |
| `include=client` (none allowed) | `The 'client' include is not allowed. No includes are allowed.`                       |
| `include[]=client`            | `The include parameter must be a comma-separated list.`                                 |

## Related

- [Base resource](00-base-resource.md)
- [Collections](01-collections.md)
- [Appends](03-appends.md)
- [Eager loads](../../repositories/02-eager-loads.md)
- [Index scopes](../../queries/01-index-scopes.md)
- [Errors](../02-errors.md)
