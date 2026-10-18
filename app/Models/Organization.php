<?php

namespace Core\Models;

use Core\Database\Factories\OrganizationFactory;
use Core\Queries\OrganizationQuery;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Support\Carbon;

/**
 * Class Organization.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property array|null $metadata
 * @property Carbon|null $activated_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property Collection<int, User> $users
 *
 * @method static OrganizationQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(OrganizationFactory::class)]
class Organization extends BaseModel
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'name',
    ];

    /**
     * The attributes that aren't mass-assignable.
     *
     * @var array<string>
     */
    protected $guarded = [
        'metadata',
        'activated_at',
        'deactivated_at',
        'suspended_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    /**
     * The loadable relationships for the model.
     *
     * @var array
     */
    protected $relationships = [
        'organization',
    ];

    /**
     * Bootstrap the model: its users are soft-deleted along with it (at the
     * same time), and restored along with it, while the users, which were
     * deleted before it, stay deleted. Both are done with one query, so no
     * model events of the users are fired.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::softDeleted(function (Organization $organization) {
            $organization->users()->update(['deleted_at' => $organization->getRawOriginal('deleted_at')]);
        });

        static::restored(function (Organization $organization) {
            $deletedAt = $organization->getPrevious()['deleted_at'] ?? null;

            if ($deletedAt === null) {
                return;
            }

            $organization->users()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->where('deleted_at', $deletedAt)
                ->update(['deleted_at' => null]);
        });
    }

    /**
     * Users associated with the model.
     *
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    /**
     * Determine if the organization is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Determine if the organization is deactivated.
     *
     * @return bool
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Returns id of the organization, to which the record belongs.
     *
     * @return int|null
     */
    public function orgId(): ?int
    {
        return $this->id;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return OrganizationQuery
     */
    public function newEloquentBuilder($query): OrganizationQuery
    {
        return new OrganizationQuery($query);
    }
}
