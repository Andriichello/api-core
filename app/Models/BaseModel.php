<?php

namespace Core\Models;

use Core\Models\Interfaces\HasOrgIdInterface;
use Core\Models\Interfaces\ListsColumnsInterface;
use Core\Models\Traits\HasOrgId;
use Core\Models\Traits\ListsColumns;
use Core\Queries\BaseQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as DatabaseBuilder;

/**
 * Class BaseModel.
 *
 * @method static BaseQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
class BaseModel extends Model implements
    HasOrgIdInterface,
    ListsColumnsInterface
{
    use HasOrgId;
    use ListsColumns;

    /**
     * The loadable relationships for the model.
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return int|null
     */
    public function orgId(): ?int
    {
        return data_get($this, 'organization_id');
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return BaseQuery
     */
    public function newEloquentBuilder($query): BaseQuery
    {
        return new BaseQuery($query);
    }
}
