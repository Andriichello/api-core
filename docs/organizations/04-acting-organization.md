# Acting organization

The acting organization is the one whose data a user works with in a request: their own one, or, for a super admin,
another one or all of them, as `User::actingOrgId()` and `User::actsInAllOrganizations()` tell. The index scopes of
users, organizations and audit logs are built on them; use them in the `index()` of your own queries too.

## The methods

`actingOrgId(): ?int` returns:

- for a super admin, the [current organization](01-current-organization.md) when one is set (the one named with
  `X-Organization-Id`), and their own organization otherwise;
- for everybody else, their own organization, always. A current organization never widens what they see, even when
  code sets one that isn't theirs.

`actsInAllOrganizations(): bool` is true only for a super admin without an organization of their own (an internal
one) while no organization is current. A super admin who belongs to an organization never acts in all of them.

| User                                      | No organization named      | Another organization named (`X-Organization-Id`) |
|-------------------------------------------|----------------------------|-------------------------------------------------|
| A user of an organization                 | Their own organization     | `403`                                            |
| A user without an organization            | None                       | `403`                                            |
| A super admin without an organization     | All organizations          | The named one                                    |
| A super admin of an organization          | Their own organization     | The named one                                    |

The `super-admin` role is api-core's own role above all others (see [Roles](../roles/00-overview.md)).

## How lists are scoped

The queries api-core ships scope their `index($user)` with these methods:

| Query                                    | A user who acts in an organization                         | Acting in all     |
|------------------------------------------|------------------------------------------------------------|-------------------|
| `UserQuery` (`GET /api/users`)           | The users of the acting organization, and the person's own accounts in any organization | All users |
| `OrganizationQuery`                      | The acting organization only                               | All organizations |
| `AuditLogQuery`                          | The entries of the acting organization                     | All entries       |

Without a user, each of them finds nothing. A user without an organization, who isn't a super admin, sees only their
own accounts, no organization and no audit log entries.

Organization-scoped models (`BelongsToOrganization`) don't use these methods: their global scope follows the current
organization only, so a super admin acting in all organizations sees none of their records until they name one (see
[Organization-scoped models](03-organization-scoped-models.md)). The global scope knows no user, so it can't tell a
super admin acting in all organizations from a forgotten context, and fails closed; an `index()` scope gets the user,
so it can.

## The organization of a request

`Core\Http\Requests\BaseRequest::orgId()` returns the organization a request acts in: the current one, or the user's
own one on a route without the `organization` middleware. It never reads the request's input. `UserController`
invites new users into it, so a super admin acting in all organizations invites users without an organization.

```php
// In a request of the application, as InviteUserRequest does for emails
'email' => [
    'required',
    'email',
    Rule::unique('clients', 'email')->where('organization_id', $this->orgId()),
],
```

## Writing an index scope

A model that super admins list across organizations, rather than one of a tenant, keeps its `organization_id`
without `BelongsToOrganization`, and scopes its query's `index()` the way `AuditLogQuery` does (the model returns
the query from `newEloquentBuilder()`, see [Base query](../queries/00-base-query.md)):

```php
namespace App\Queries;

use Core\Models\User;
use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Model;

class SubscriptionQuery extends BaseQuery
{
    /**
     * @param User|null $user
     */
    public function index(?Model $user, array $params = []): SubscriptionQuery
    {
        if ($user?->actsInAllOrganizations()) {
            return $this;
        }

        $orgId = $user?->actingOrgId();

        if ($orgId === null) {
            $this->whereRaw('1 = 0');

            return $this;
        }

        $this->where($this->qualifyColumn('organization_id'), $orgId);

        return $this;
    }
}
```

Return nothing, rather than everything, when there is no organization to scope by, as the shipped queries do.

## Related

- [Organizations](00-overview.md)
- [Current organization](01-current-organization.md)
- [Resolving the organization](02-resolving-the-organization.md)
- [Organization-scoped models](03-organization-scoped-models.md)
- [Index scopes](../queries/01-index-scopes.md)
- [Managing users](../users/01-managing-users.md)
- [Reading and pruning the audit log](../audit/04-reading-and-pruning.md)
- [Roles](../roles/00-overview.md)
