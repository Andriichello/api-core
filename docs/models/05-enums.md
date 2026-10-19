# Enums

api-core's enums are string- or int-backed PHP enums with a few list helpers, which the `BaseEnum` trait adds. Use the
same trait for the application's own enums, such as the status of an invoice.

## BaseEnum

`Core\Enums\Traits\BaseEnum` adds three static methods to a backed enum, and
`Core\Enums\Interfaces\BaseEnumInterface` (which extends `BackedEnum`) declares them:

| Method | Returns |
|--------|---------|
| `names()` | The case names, e.g. `['Draft', 'Sent']`. |
| `values()` | The case values, e.g. `['draft', 'sent']`. |
| `list()` | The names mapped to the values, e.g. `['Draft' => 'draft', 'Sent' => 'sent']`. |

The application's enum:

```php
namespace App\Enums;

use Core\Enums\Interfaces\BaseEnumInterface;
use Core\Enums\Traits\BaseEnum;

enum InvoiceStatus: string implements BaseEnumInterface
{
    use BaseEnum;

    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
```

`values()` fits validation rules, such as `Rule::in(InvoiceStatus::values())`, though Laravel's
`Rule::enum(InvoiceStatus::class)` works too. Cast a column to the enum with `$casts`; [status
transitions](03-status-transitions.md) compare such statuses by their values.

## The enums api-core ships

### UserRole

`Core\Enums\UserRole` (string) names the built-in roles: `Guest` (`guest`), `User` (`user`), `Admin` (`admin`) and
`SuperAdmin` (`super-admin`). The super admin is api-core's own role above all others, which needs no permissions; the
other three are the default roles of `api-core.roles`, which an application may replace with its own. `User` checks
them with `isGuest()`, `isUser()`, `isAdmin()` and `isSuperAdmin()`. See [Roles](../roles/00-overview.md).

### OrgContext

`Core\Enums\OrgContext` (int) names the contexts of records that don't belong to a tenant organization, by their
`organization_id`:

| Case | Value | Meaning |
|------|-------|---------|
| `Global` | `-1` | Shared with all tenants, read-only for them. |
| `Internal` | `0` | api-core's own records, for internal and system operations. |

A record without an organization (`null`), such as a super admin, counts as internal too. `orgContext()` of a model
answers with a case, `'tenant'` for a positive id, or `'unknown'` for another negative one. See
[Organization-scoped models](../organizations/03-organization-scoped-models.md).

### FilterOperation and FilterFlag

`Core\Enums\FilterOperation` (string) lists the operations of the `filter` query parameter: `eq`, `in`, `null`,
`like`, `contains`, `gt`, `gte`, `lt` and `lte`. Its cases are upper-case (`FilterOperation::EQ`).

`Core\Enums\FilterFlag` (string) lists the flags that modify them: `Not` (`not`) negates the operation and
`CaseInsensitive` (`ci`) ignores the case.

```http
GET /api/invoices?filter[status][not][in]=draft,cancelled
```

See [Filter operations](../querying/01-filter-operations.md) for what each one does.

## Related

- [Status transitions](03-status-transitions.md)
- [Roles](../roles/00-overview.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Filter operations](../querying/01-filter-operations.md)
