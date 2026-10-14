<?php

namespace Core\Repositories;

use Core\Enums\FilterOperation;
use Core\Http\Filters\BaseFilter;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;
use Core\Queries\Interfaces\IndexableInterface;
use Core\Repositories\Interfaces\CrudRepositoryInterface;
use Core\Repositories\Traits\AllowsEagerLoads;
use Core\Repositories\Traits\AllowsFilters;
use Core\Repositories\Traits\AllowsSorts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

/**
 * Class CrudRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
abstract class CrudRepository extends BaseRepository implements CrudRepositoryInterface
{
    use AllowsSorts;
    use AllowsFilters;
    use AllowsEagerLoads;

    /**
     * Get the query builder for the repository's model.
     *
     * @param Request|null $request
     *
     * @return BaseQuery
     * @throws Throwable
     */
    public function builder(?Request $request = null): BaseQuery
    {
        return $this->applyRequestOnQuery(parent::builder(), $request);
    }

    /**
     * Apply `sorts`, `filters` and `index` rules on query.
     *
     * @param BaseQuery $query
     * @param Request|null $request
     *
     * @return BaseQuery
     * @throws Throwable
     */
    protected function applyRequestOnQuery(BaseQuery $query, ?Request $request = null): BaseQuery
    {
        $request ??= request();

        if ($query instanceof IndexableInterface) {
            $query->index($request->user(), $request->all());
        }

        if ($request) {
            $sorts = $this->getRequestedSorts($request);

            if (empty($sorts)) {
                $sorts = $this->getDefaultSorts();
            }

            foreach ($sorts as $sort => $direction) {
                $table = $this->tableOfAllowedSort($sort) ?? $query->getModel()->getTable();
                $column = $this->columnOfAllowedSort($sort) ?? $sort;

                $query->orderBy(
                    empty($table) ? $column : $table . '.' . $column,
                    $direction
                );
            }

            $filters = $this->getRequestedFilters($request);

            foreach ($filters as $filter) {
                $field = $filter['field'];
                $value = $filter['value'] ?? '';

                // An empty value (`filter[name][eq]=`) filters nothing, except for
                // the null operation, which does not use the value at all
                if ($value === '' && $filter['operation'] !== FilterOperation::NULL) {
                    continue;
                }

                /** @var class-string<BaseFilter> $class */
                $class = $this->classOfAllowedFilter($field);
                $table = $this->tableOfAllowedFilter($field);
                $column = $this->columnOfAllowedFilter($field);

                $logic = new $class($table ?? $query->getModel()->getTable())
                    ->setOperation($filter['operation'])
                    ->setFlags($filter['flags'] ?? []);

                $logic->apply($query, $column ?? $field, $value);
            }

            $includes = $this->getValidatedIncludes($request);

            if (!empty($includes)) {
                $query->with($includes);
            }
        }

        return $query;
    }

    /**
     * Find the model by the id.
     *
     * @param mixed $id
     *
     * @return BaseModel|null
     */
    public function find(mixed $id): ?BaseModel
    {
        return $this->builder()->find($id);
    }

    /**
     * Find the model by the id or throw an exception.
     *
     * @param mixed $id
     *
     * @return BaseModel|null
     */
    public function findOrFail(mixed $id): ?BaseModel
    {
        return $this->builder()->findOrFail($id);
    }

    /**
     * Create the model with given attributes.
     *
     * @param array $attributes
     *
     * @return BaseModel
     */
    public function create(array $attributes): BaseModel
    {
        return $this->builder()->create($attributes);
    }

    /**
     * Update the model with given attributes.
     *
     * @param BaseModel $model
     * @param array $attributes
     *
     * @return bool
     */
    public function update(BaseModel $model, array $attributes): bool
    {
        return $model->update($attributes);
    }

    /**
     * Update or create the model with given attributes.
     *
     * @param array $attributes
     *
     * @return Builder|BaseModel
     */
    public function updateOrCreate(array $attributes): Builder|BaseModel
    {
        return $this->builder()->updateOrCreate($attributes);
    }

    /**
     * Delete the given model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function delete(BaseModel $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Force delete the given model. It is deleted with `delete()` first, so
     * any logic of deleting runs for it too, and then removed permanently.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function forceDelete(BaseModel $model): bool
    {
        if (!$this->delete($model)) {
            return false;
        }

        // Without soft deletes, delete() has already removed the model for good
        if (!$this->isSoftDeletable()) {
            return true;
        }

        return (bool) $model->forceDelete();
    }
}
