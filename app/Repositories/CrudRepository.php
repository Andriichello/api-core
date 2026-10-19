<?php

namespace Core\Repositories;

use Core\Enums\FilterOperation;
use Core\Http\Filters\BaseFilter;
use Core\Http\Filters\DateFilter;
use Core\Models\BaseModel;
use Core\Queries\BaseQuery;
use Core\Queries\Interfaces\IndexableInterface;
use Core\Repositories\Interfaces\CrudRepositoryInterface;
use Core\Repositories\Traits\AllowsEagerLoads;
use Core\Repositories\Traits\AllowsFilters;
use Core\Repositories\Traits\AllowsSorts;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Class CrudRepository.
 *
 * `builder($request)` applies the request's index scope, filters, sorts and
 * includes. The other methods use the model's plain query, so they find and
 * write the same, whatever request or console command they run in.
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

                // An empty value (`filter[name][eq]=`) filters nothing, except
                // for the null operation, for which it means `is null`
                if ($value === '' && $filter['operation'] !== FilterOperation::NULL) {
                    continue;
                }

                /** @var class-string<BaseFilter> $class */
                $class = $this->classOfAllowedFilter($field);
                $table = $this->tableOfAllowedFilter($field);
                $column = $this->columnOfAllowedFilter($field);

                try {
                    // A filter of one operation refuses the others
                    $logic = new $class($table ?? $query->getModel()->getTable())
                        ->setOperation($filter['operation'])
                        ->setFlags($filter['flags'] ?? []);

                    // Dates without an offset are read in the user's timezone
                    if ($logic instanceof DateFilter) {
                        $logic->setTimezone(data_get($request->user(), 'timezone'));
                    }

                    $logic->apply($query, $column ?? $field, $value);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        "filter.$field" => [$exception->getMessage()],
                    ]);
                }
            }

            $includes = $this->getValidatedIncludes($request);

            if (!empty($includes)) {
                $query->with($this->scopedEagerLoads($includes, $request->user()));
            }
        }

        return $query;
    }

    /**
     * Find the model by the id, without the index scope, filters and sorts
     * of the current request.
     *
     * @param mixed $id
     *
     * @return BaseModel|null
     */
    public function find(mixed $id): ?BaseModel
    {
        return parent::builder()->find($id);
    }

    /**
     * Find the model by the id, without the index scope, filters and sorts
     * of the current request, or throw an exception.
     *
     * @param mixed $id
     *
     * @return BaseModel
     * @throws ModelNotFoundException
     */
    public function findOrFail(mixed $id): BaseModel
    {
        return parent::builder()->findOrFail($id);
    }

    /**
     * Create the model with given attributes, which belongs to the parents:
     * the models, which its BelongsTo relations, named by the keys, point to.
     *
     * @param array $attributes
     * @param array<string, BaseModel> $parents
     *
     * @return BaseModel
     */
    public function create(array $attributes, array $parents = []): BaseModel
    {
        /** @var BaseModel $model */
        $model = parent::builder()->newModelInstance($attributes);

        foreach ($parents as $relation => $parent) {
            $model->$relation()->associate($parent);
        }

        $model->save();

        return $model;
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
     * Update the model, which matches the given attributes, with the values,
     * or create it with both.
     *
     * @param array $attributes
     * @param array $values
     *
     * @return BaseModel
     */
    public function updateOrCreate(array $attributes, array $values = []): BaseModel
    {
        /** @var BaseModel $model */
        $model = parent::builder()->updateOrCreate($attributes, $values);

        return $model;
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
     * Restore the given soft-deleted model.
     *
     * @param BaseModel $model
     *
     * @return bool
     */
    public function restore(BaseModel $model): bool
    {
        return method_exists($model, 'restore') && $model->restore();
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
