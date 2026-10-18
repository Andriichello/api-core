<?php

namespace Core\Queries;

use Core\Models\Sequence;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SequenceQuery.
 *
 * @method SequenceQuery scopes($scopes)
 *
 * @method Sequence|null find($id, $columns = ['*'])
 * @method Sequence findOrFail($id, $columns = ['*'])
 * @method Sequence|null first($columns = ['*'])
 * @method Sequence firstOrFail($columns = ['*'])
 * @method Sequence sole($columns = ['*'])
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries
 */
class SequenceQuery extends BaseQuery
{
    /**
     * Limit the query to the owner's sequence under the key.
     *
     * @param Model $owner
     * @param string $key
     *
     * @return SequenceQuery
     */
    public function whereOwnerAndKey(Model $owner, string $key): SequenceQuery
    {
        $this->whereMorphedTo('model', $owner)
            ->where($this->qualifyColumn('key'), $key);

        return $this;
    }
}
