# Appends

The `append` query parameter adds attributes, which a resource allows, to the output of a model: accessors that cost
something to compute, or values a client needs only sometimes, such as a temporary download URL.

## Allowing appends

A resource lists its appends in `$allowedAppends`. The value of an append is read from the model, so it can be a
column, an attribute or an accessor:

```php
class InvoiceResource extends BaseResource
{
    protected array $allowedAppends = ['balance'];
}
```

```php
class Invoice extends BaseModel
{
    protected function balance(): Attribute
    {
        return Attribute::get(fn () => $this->total - $this->paid);
    }
}
```

An attribute in the model's own `$appends` is always output; one in the resource's `$allowedAppends` only on request.
Reading an append loads nothing for it, so an accessor that reads a relation queries it for each model of a list.

## Requesting appends

`append` is a comma-separated list; a name given twice is added once.

```http
GET /api/invoices/7?append=balance
```

```json
{
    "data": {
        "id": 7,
        "number": "INV-0007",
        "total": 1250,
        "paid": 500,
        "balance": 750
    },
    "message": "OK"
}
```

(The invoice shows a few of its attributes.) In a list, every item gets the appends. api-core's `FileResource` allows
`url`, a temporary URL that downloads the file (see [Downloads and URLs](../../files/04-downloads-and-urls.md)).

## Nested appends

An append with a dot goes to an include: `append=client.balance` asks the included client for its `balance`, so
`ClientResource` has to allow it. It applies to every item of a to-many include, and paths go deeper, such as
`lines.product.price`.

```http
GET /api/invoices/7?include=client&append=balance,client.balance
```

The appends of the top level aren't passed to the included records: `append=balance` adds the invoice's balance only.

A nested append is ignored when its relation isn't included. Under a relation that is included without a resource,
which allows no appends, it answers `422`.

## Errors

An append that isn't allowed answers `422` with an `append` error, which names the allowed appends:

```json
{
    "message": "The 'total_due' append is not allowed. Allowed appends: balance.",
    "errors": {
        "append": [
            "The 'total_due' append is not allowed. Allowed appends: balance."
        ]
    }
}
```

| Request                                     | Message                                                              |
|---------------------------------------------|----------------------------------------------------------------------|
| `append=total_due`                          | `The 'total_due' append is not allowed. Allowed appends: balance.`  |
| `append=balance` (none allowed)             | `The 'balance' append is not allowed. No appends are allowed.`       |
| `include=tags&append=tags.name` (no resource) | `The 'name' append is not allowed. No appends are allowed.`        |
| `append[]=balance`                          | `The append parameter must be a comma-separated list.`               |

A nested append is named in the message without the path of its include.

Appends are checked when the response is built. `CrudController` checks the appends and includes of `store`, `update`
and `restore` against its resource before they change anything (`BaseResource::validateRequested()`), so their `422`
comes before the change. An append under a relation, which isn't included, is left out, not refused.

## Related

- [Base resource](00-base-resource.md)
- [Collections](01-collections.md)
- [Includes](02-includes.md)
- [Errors](../02-errors.md)
- [Downloads and URLs](../../files/04-downloads-and-urls.md)
