<?php

namespace Core\Models\Traits;

use Core\Helpers\CurrentOrganization;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Trait BelongsToOrganization.
 *
 * For models of an organization (with `organization_id`): a global scope
 * limits their queries to CurrentOrganization, and to no records at all,
 * when it isn't set, so a forgotten context fails closed. A new record gets
 * the current organization, and a record of another organization can't be
 * saved in it. Without a current organization (console, seeders), records
 * are saved with the organization, which they are given. Code, which works
 * across organizations, removes the `organization` scope explicitly.
 *
 * @mixin BaseModel
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait BelongsToOrganization
{
    /**
     * Name of the global scope, which limits queries to the organization.
     *
     * @var string
     */
    public const string ORGANIZATION_SCOPE = 'organization';

    /**
     * Boot the trait, registering its global scope and model event listeners.
     *
     * @return void
     */
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(self::ORGANIZATION_SCOPE, function (Builder $query) {
            $id = CurrentOrganization::id();

            if ($id === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->qualifyColumn('organization_id'), $id);
        });

        static::saving(fn (self $model) => $model->assignCurrentOrganization());
    }

    /**
     * Organization, to which the model belongs.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Give the model the current organization, when it has none, and make
     * sure it isn't saved in an organization, which isn't the current one.
     *
     * @return void
     * @throws LogicException
     */
    protected function assignCurrentOrganization(): void
    {
        $current = CurrentOrganization::id();
        $organizationId = $this->getAttribute('organization_id');

        if ($organizationId === null) {
            if ($current === null) {
                throw new LogicException(static::class . ' has no organization, and none is current.');
            }

            $this->setAttribute('organization_id', $current);

            return;
        }

        if ($current !== null && (int) $organizationId !== $current) {
            throw new LogicException(static::class . ' of another organization can\'t be saved in the current one.');
        }
    }
}
