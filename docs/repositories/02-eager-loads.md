# Eager loads

The `Core\Repositories\Traits\AllowsEagerLoads` trait of `CrudRepository` turns the `include` query parameter into eager
loads: it checks each include against the repository's resource, and loads only the related records that the user may
list. An include never reaches more than listing those records would.

## Setup

Name the resource whose allowed includes the repository checks:

```php
class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected string $resourceClass = InvoiceResource::class;
}
```

The resource lists the includes, and the resources they are shown with (see
[Includes](../http/resources/02-includes.md)):

```php
class InvoiceResource extends BaseResource
{
    protected array $allowedIncludes = [
        'client' => ClientResource::class,
        'items' => InvoiceItemCollection::class,
    ];
}
```

With the default `$resourceClass` (`BaseResource` itself), or any class that doesn't extend `BaseResource`, the
repository ignores `include`.

## Validating includes

`getValidatedIncludes(Request $request): array` reads `include` as a comma-separated list, and adds every level of a
nested include: `client.projects` asks for `client` and `client.projects`. Each one must be allowed by the resource,
and a nested one by the resource of the relation it is under. Otherwise it answers 422 on `include`:

```http
GET /api/invoices?include=creator
```

```json
{
    "message": "The 'creator' include is not allowed. Allowed includes: client, items.",
    "errors": {
        "include": ["The 'creator' include is not allowed. Allowed includes: client, items."]
    }
}
```

| Case | Message |
|------|---------|
| An include the resource doesn't allow | `The 'creator' include is not allowed. Allowed includes: client, items.` |
| Any include, when the resource allows none | `The 'creator' include is not allowed. No includes are allowed.` |
| A nested include under a relation without a resource | `The 'client.projects' include is not allowed. No includes are allowed under 'client'.` |
| `include` isn't a string (`include[]=client`) | `The include parameter must be a comma-separated list.` |

## Scoping the loaded records

`scopedEagerLoads(array $includes, ?Model $user): array` maps each include to a constraint that applies the related
query's `index($user)` (see [Index scopes](../queries/01-index-scopes.md)). `CrudRepository::builder()` passes the
validated includes to `with()` through it, so:

- a to-one relation to a record the user can't list is `null`;
- a to-many relation leaves out the records the user can't list;
- a `morphTo` relation scopes the query of each type it loads with that type's own `index()`;
- a nested include is scoped at every level, since every level is in the list.

For example, on a list of invoices with `?include=client`, an invoice whose client the user may not list has `null` as
its client.

The scope is only as strict as the related model's query: a model without a query class of its own uses `BaseQuery`'s
`index()`, which adds no conditions, so all of its related records are loaded (within its global scopes).

## Using it in code

Load relations of models that were fetched otherwise with the same scoping:

```php
$invoice->load($repository->scopedEagerLoads(['client', 'client.projects'], $request->user()));
```

Name every level of a nested relation, as `getValidatedIncludes()` does: Laravel loads `client` of `client.projects`
without the constraint, unless `client` is in the list too.

## Related

- [Includes](../http/resources/02-includes.md)
- [The CRUD repository](01-crud-repository.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Base resource](../http/resources/00-base-resource.md)
