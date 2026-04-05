<?php

namespace Core\Models;

use Core\Models\Interfaces\HasOrgIdInterface;
use Core\Models\Interfaces\ListsColumnsInterface;
use Core\Models\Traits\HasOrgId;
use Core\Models\Traits\ListsColumns;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BaseModel.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
class BaseModel extends Model implements
    HasOrgIdInterface,
    ListsColumnsInterface
{
    use HasOrgId, ListsColumns;

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
}
