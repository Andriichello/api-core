# Index scopes

A query's `index($user)` limits it to the records that the user may list. api-core applies it wherever a request reads
records: lists, single records, parents of nested routes and includes. Write one for every model whose records not
every user may see.

## The interface

`Core\Queries\Interfaces\IndexableInterface` declares:

```php
public function index(?Model $user, array $params = []): BaseQuery;
```

`BaseQuery` implements it without conditions, so a model without a query class of its own is listed in full (within
its global scopes, such as the organization scope of `BelongsToOrganization`).

## Where it is applied

| Caller | User | `$params` |
|--------|------|-----------|
| `CrudRepository::builder($request)`: index, show, update, destroy and restore | `$request->user()` | `$request->all()` |
| `CrudController` finding the parents of a nested route | `$request->user()` | `[]` |
| `AllowsEagerLoads::scopedEagerLoads()`: every relation of `?include=` | the request's user | `[]` |
| `WithTarget::targetQuery()`: the target of a request | `$request->user()` | `$request->all()` |

A record outside the scope is therefore missing: showing, updating or deleting it answers 404 (`Not found.`), a nested
route under it answers 404, and an include leaves it out or gives `null`. Use `$params` only to narrow a list further,
never to grant access, since some callers don't pass it.

## Writing one

Return nothing for a guest, so a forgotten middleware fails closed, and narrow the query by what the user may see:

```php
namespace App\Queries;

use Core\Models\User;
use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Model;

class InvoiceQuery extends BaseQuery
{
    /**
     * Apply index query conditions for a given user: all invoices of the organization for its admins, and the
     * ones they created for other users.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return InvoiceQuery
     */
    public function index(?Model $user, array $params = []): InvoiceQuery
    {
        if (!$user) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        if (!$user->hasRightsOfAdmin()) {
            $this->where($this->qualifyColumn('created_by_user_id'), $user->getKey());
        }

        return $this;
    }
}
```

Here `Invoice` uses `BelongsToOrganization`, whose global scope already keeps the records of other organizations out.
For a model that doesn't, limit it to `$user->actingOrgId()`, unless `$user->actsInAllOrganizations()` (see
[The acting organization](../organizations/04-acting-organization.md)). Qualify the columns, since the query may be
joined or used as a relation's query.

## The queries api-core ships

| Query | `index($user)` |
|-------|----------------|
| `UserQuery` | No users for a guest; all users for a super admin acting in all organizations; otherwise the person's own accounts (the main account and the accounts linked to it) and the users of the organization the user acts in. A user without an organization sees only their own accounts. |
| `OrganizationQuery` | None for a guest; all for a super admin acting in all organizations; otherwise the organization the user acts in, and none without one. |
| `AuditLogQuery` | All entries for a super admin acting in all organizations; otherwise the entries of the organization the user acts in, and none without one (guests included). |
| `FileQuery` | None for a guest; otherwise not limited further, since files are reached through the models they are attached to, which are scoped by their own queries. |
| `SequenceQuery` | `BaseQuery`'s: no conditions. Sequences aren't listed through the API. |

A super admin who acts in a particular organization (such as with `X-Organization-Id`) is limited to it like its users.
Other users always act in their own organization, so a current organization never widens what they see.

They also add query methods:

- `UserQuery::ofPerson(User $user)` and `ofPeople(User ...$users)`: the accounts of the people who own the given
  accounts (see [Linked accounts](../auth/06-linked-accounts.md)).
- `FileQuery::inCollection(string $collection)`: the files in a collection.
- `AuditLogQuery::ofSubject(Model $subject)`: the entries of a model.
- `SequenceQuery::whereOwnerAndKey(Model $owner, string $key)`: the owner's sequence under the key.

## Related

- [Base query](00-base-query.md)
- [The acting organization](../organizations/04-acting-organization.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [The CRUD repository](../repositories/01-crud-repository.md)
- [Eager loads](../repositories/02-eager-loads.md)
- [Nested routes](../http/controllers/02-nested-routes.md)
- [Targets](../http/requests/02-targets.md)
- [The user model](../users/00-user-model.md)
