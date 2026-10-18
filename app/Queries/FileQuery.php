<?php

namespace Core\Queries;

use Core\Models\File;
use Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FileQuery.
 *
 * @method FileQuery scopes($scopes)
 *
 * @method File|null find($id, $columns = ['*'])
 * @method File findOrFail($id, $columns = ['*'])
 * @method File|null first($columns = ['*'])
 * @method File firstOrFail($columns = ['*'])
 * @method File create(array $attributes = [])
 * @method File forceCreate(array $attributes)
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Queries
 */
class FileQuery extends BaseQuery
{
    /**
     * Apply index query conditions for a given user: no files for a guest.
     * Files aren't scoped any further here, since they are reached through
     * the models, which they are attached to (see FileController), and which
     * are scoped by their own queries.
     *
     * @param User|null $user
     * @param array $params
     *
     * @return FileQuery
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function index(?Model $user, array $params = []): FileQuery
    {
        if (!$user) {
            $this->whereRaw('1 = 0');
        }

        return $this;
    }

    /**
     * Limit the query to the files in the given collection.
     *
     * @param string $collection
     *
     * @return FileQuery
     */
    public function inCollection(string $collection): FileQuery
    {
        $this->where($this->qualifyColumn('collection'), $collection);

        return $this;
    }
}
