# Building a feature

An end-to-end example of an organization-scoped `Invoice` of a `Client`: this page builds its model, query, repository,
policy and permissions, and [the endpoints](07-building-a-feature-endpoints.md) add its requests, resource, controller
and routes. Follow both when adding a model of your own.

## The pieces

| Piece                   | Class                                                         | Covered in                                                       |
|-------------------------|---------------------------------------------------------------|------------------------------------------------------------------|
| Model                   | `App\Models\Invoice`                                          | [Organization-scoped models](../organizations/03-organization-scoped-models.md) |
| Query                   | `App\Queries\InvoiceQuery`                                    | [Index scopes](../queries/01-index-scopes.md)                    |
| Repository              | `App\Repositories\InvoiceRepository`                          | [CRUD repository](../repositories/01-crud-repository.md)         |
| Policy                  | `App\Policies\InvoicePolicy`                                  | [CRUD policy](../http/policies/01-crud-policy.md)                |
| Permissions             | `config/api-core.php`                                         | [Configuring roles](../roles/01-configuring-roles.md)            |
| Requests                | `App\Http\Requests\Invoices\StoreInvoiceRequest`, `UpdateInvoiceRequest` | [CRUD requests](../http/requests/01-crud-requests.md) |
| Resource and collection | `App\Http\Resources\InvoiceResource`, `InvoiceCollection`     | [Base resource](../http/resources/00-base-resource.md)           |
| Controller and routes   | `App\Http\Controllers\InvoiceController`                      | [CrudController](../http/controllers/01-crud-controller.md)      |

## Table and model

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('client_id')->constrained()->cascadeOnDelete();
    $table->string('number');
    $table->string('status')->default('draft');
    $table->decimal('total', 12, 2);
    $table->date('due_on')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

```php
namespace App\Models;

use App\Queries\InvoiceQuery;
use Core\Models\BaseModel;
use Core\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static InvoiceQuery query()
 */
class Invoice extends BaseModel
{
    use BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = ['client_id', 'number', 'status', 'total', 'due_on'];

    protected $attributes = ['status' => 'draft'];

    protected $casts = ['total' => 'decimal:2', 'due_on' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function newEloquentBuilder($query): InvoiceQuery
    {
        return new InvoiceQuery($query);
    }
}
```

`BelongsToOrganization` limits every query to the current organization (to no records without one) and gives a new
record the current organization, so `organization_id` stays out of `$fillable`. `$attributes` puts the default status
into the model, so a created invoice shows it without being loaded again. `Client` is a `BaseModel` with the same
trait.

Add both models to the morph map in a service provider's `boot()`, so polymorphic columns (files, settings, the audit
log) store `models.invoices` and `models.clients`, the names that permissions use too:

```php
use Core\Providers\MorphServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap(MorphServiceProvider::getMorphMap([Client::class, Invoice::class]));
```

## Query

```php
namespace App\Queries;

use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Model;

class InvoiceQuery extends BaseQuery
{
    public function index(?Model $user, array $params = []): InvoiceQuery
    {
        // The organization scope applies already, and guests see nothing
        if (!$user) {
            $this->whereRaw('1 = 0');
        }

        return $this;
    }
}
```

`index()` decides which invoices a user may list. The same scope finds the record of a show, update or delete (others
are a 404), and limits invoices included in other models' responses.

## Repository

```php
namespace App\Repositories;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Core\Models\BaseModel;
use Core\Repositories\CrudRepository;

class InvoiceRepository extends CrudRepository
{
    protected BaseModel|string $model = Invoice::class;

    protected string $resourceClass = InvoiceResource::class;

    protected array $defaultSorts = ['due_on' => 'desc'];
}
```

The repository checks `?include=` against `$resourceClass`, and allows `filter` and `sort` on the fillable columns,
the key and the timestamps (see [Filters](../querying/00-filters.md) and [Sorting](../querying/04-sorting.md)).

## Policy and permissions

```php
namespace App\Policies;

use App\Models\Invoice;
use Core\Models\BaseModel;
use Core\Policies\CrudPolicy;

class InvoicePolicy extends CrudPolicy
{
    protected BaseModel|string $model = Invoice::class;

    public function update(mixed $user, mixed $model): bool
    {
        return parent::update($user, $model) && $model->status !== 'paid';
    }
}
```

A user needs the `{ability} models.invoices` permission (super admins skip it), and then the ability's method decides:
`CrudPolicy` lets any role but `guest` create, update, delete and restore. Grant the permissions in
`config/api-core.php`. Its `roles` list replaces api-core's as a whole, so keep api-core's entries:

```php
use App\Models\Invoice;
use Core\Models\Organization;
use Core\Models\User;
use Spatie\Permission\Models\Role;

return [
    'roles' => [
        ['name' => 'admin', 'permissions' => [
            User::class => ['all', 'attachRole', 'attachAnyRole', 'detachRole'],
            Organization::class => ['viewAny', 'view', 'update'],
            Role::class => ['attachUser', 'attachAnyUser', 'detachUser'],
            Invoice::class => ['all', 'restore'],
        ]],
        ['name' => 'user', 'permissions' => [
            User::class => ['viewAny', 'view'],
            Organization::class => ['viewAny', 'view'],
            Invoice::class => ['viewAny', 'view'],
        ]],
        ['name' => 'guest', 'permissions' => [
            User::class => ['viewAny', 'view'],
            Organization::class => ['viewAny', 'view'],
        ]],
    ],
];
```

`all` stands for `viewAny`, `view`, `create`, `update` and `delete`, so `restore` is listed on its own. Sync the
database with the list:

```bash
php artisan db:seed --class="Core\Database\Seeders\RolesAndPermissionsSeeder"
```

It creates the `viewAny`, `view`, `create`, `update`, `delete` and `restore` permissions of `models.invoices`.

Continue with [the endpoints](07-building-a-feature-endpoints.md).

## Related

- [Building a feature: the endpoints](07-building-a-feature-endpoints.md)
- [Overview](00-overview.md)
- [Organization-scoped models](../organizations/03-organization-scoped-models.md)
- [Index scopes](../queries/01-index-scopes.md)
- [CRUD repository](../repositories/01-crud-repository.md)
- [CRUD policy](../http/policies/01-crud-policy.md)
- [Configuring roles](../roles/01-configuring-roles.md)
