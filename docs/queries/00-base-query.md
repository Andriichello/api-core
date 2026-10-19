# Base query

`Core\Queries\BaseQuery` is the Eloquent query builder of every `BaseModel`. It adds the index scope and a few helpers
to Laravel's builder. Write a subclass for a model when it needs its own index scope or query methods.

## A model's query class

`BaseModel::newEloquentBuilder()` returns a `BaseQuery`. A model with its own query class overrides it, and declares
`query()` for static analysis:

```php
namespace App\Queries;

use Core\Queries\BaseQuery;

class InvoiceQuery extends BaseQuery
{
    public function overdue(): static
    {
        $this->where($this->qualifyColumn('due_at'), '<', now())
            ->whereNull($this->qualifyColumn('paid_at'));

        return $this;
    }
}
```

```php
namespace App\Models;

use App\Queries\InvoiceQuery;
use Core\Models\BaseModel;

/**
 * @method static InvoiceQuery query()
 */
class Invoice extends BaseModel
{
    public function newEloquentBuilder($query): InvoiceQuery
    {
        return new InvoiceQuery($query);
    }
}
```

api-core's models do the same with `UserQuery`, `OrganizationQuery`, `FileQuery`, `AuditLogQuery` and `SequenceQuery`.

## Helpers

| Method | Description |
|--------|-------------|
| `index(?Model $user, array $params = [])` | Limits the query to what the user may list. `BaseQuery`'s own adds no conditions; see [Index scopes](01-index-scopes.md). |
| `whereTrashed()` | Only the soft-deleted records, or none for a model without soft deletes. |
| `whereWrapped(Closure $callback, string $boolean = 'and')` | Adds the conditions that the callback puts on a fresh query of the model as one parenthesized group. |
| `forWrappedWhere()` | The fresh query of the model, which `whereWrapped()` passes to its callback. |
| `substituteWhere(string $column, string $substitution)` | Renames a column in the query's where clauses, nested ones included. |
| `getScopes()`, `hasScope(string $scope)` | The global scopes of the query, keyed by their names, and whether it has the given one (such as `'organization'`). |
| `getJoins()`, `getJoinAlias(string $table)`, `hasJoin(string $table, ?string $alias = null)` | The joined tables (aliases mapped to table names), the alias of a joined table, and whether a table is joined. |
| `chunkPaginated(callable $callback, int $count = 1000)` | Chunks the results within the query's offset and limit. |

`lockForUpdate()`, `limit()` and `offset()` are Laravel's; `BaseQuery` declares them as returning the query itself, so
static analysis keeps the query's class through chains such as `Sequence::query()->whereOwnerAndKey(...)
->lockForUpdate()`.

### whereTrashed()

`whereTrashed()` removes the soft-deleting scope and keeps the records whose `deleted_at` is set. It's how the `restore`
operation of a CRUD controller finds what to restore. For a model without `SoftDeletes` it adds `1 = 0`, so it finds
nothing rather than everything:

```php
User::query()->whereTrashed()->pluck('id'); // the deleted users
File::query()->whereTrashed()->exists();    // false: files can't be soft-deleted
```

### substituteWhere()

`substituteWhere()` helps when a query, which was filtered by a bare column name, is joined later and the name becomes
ambiguous:

```php
$query->join('clients', 'clients.id', '=', 'invoices.client_id')
    ->substituteWhere('name', 'clients.name');
```

Only where clauses that have a column (such as `=`, `in` and `null`) are renamed; raw ones are left as they are.

### chunkPaginated()

`chunkPaginated()` goes through the results in chunks within the query's offset and limit: the chunks start at the
offset and stop at the limit, or at the end when there is no limit. Each chunk is fetched with a clone of the query, so
the query keeps its own offset and limit. An empty chunk is never passed to the callback, and the callback returning
`false` stops chunking:

```php
use Illuminate\Database\Eloquent\Collection;

Invoice::query()
    ->orderBy('id')
    ->offset(100)
    ->limit(250)
    ->chunkPaginated(function (Collection $invoices) {
        // 100 invoices, 100 more, then the last 50
    }, 100);
```

It pages with offsets and, unlike Laravel's `chunk()`, doesn't order the query by its key, so give the query a stable
order and don't change the rows it pages through.

## Related

- [Index scopes](01-index-scopes.md)
- [Base model](../models/00-base-model.md)
- [The CRUD repository](../repositories/01-crud-repository.md)
- [Restoring](../http/controllers/03-restoring.md)
- [Paginating in code](../pagination/02-paginating-in-code.md)
- [Sequences](../models/04-sequences.md)
