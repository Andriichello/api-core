<?php

namespace Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Core\Models\Traits\ChecksRolesAndPermissions;
use Core\Queries\UserQuery;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * Class User.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $main_account_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $timezone
 * @property string $locale
 * @property array|null $metadata
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property Organization|null $organization
 * @property User|null $mainAccount
 * @property Collection<int, User> $relatedAccounts
 * @property Collection<int, Role> $roles
 * @property Collection<int, Permission> $permissions
 * @property Collection<int, DatabaseNotification> $notifications
 *
 * @method static UserQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
class User extends BaseModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;
    use HasRoles;
    use ChecksRolesAndPermissions;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
    ];

    /**
     * The attributes that aren't mass-assignable.
     *
     * @var array<string>
     */
    protected $guarded = [
        'organization_id',
        'main_account_id',
        'metadata',
        'email_verified_at',
        'suspended_at',
        'last_login_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'main_account_id',
    ];

    /**
     * The loadable relationships for the model.
     *
     * @var array
     */
    protected $relationships = [
        'organization',
        'mainAccount',
        'relatedAccounts',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Organization associated with the model.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Main account of the person, to which this account is linked.
     *
     * @return BelongsTo
     */
    public function mainAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'main_account_id');
    }

    /**
     * Accounts of the person, which are linked to this main account.
     *
     * @return HasMany
     */
    public function relatedAccounts(): HasMany
    {
        return $this->hasMany(User::class, 'main_account_id');
    }

    /**
     * Determine if this is a main account, which isn't linked to another one.
     *
     * @return bool
     */
    public function isMainAccount(): bool
    {
        return $this->main_account_id === null;
    }

    /**
     * Get id of the person's main account: the one this account
     * is linked to, or its own id, when it is the main account.
     *
     * @return int
     */
    public function mainAccountId(): int
    {
        return $this->main_account_id ?? $this->id;
    }

    /**
     * Determine if the given account belongs to the same person: it is this
     * account, its main account, or another account linked to the same one.
     *
     * @param User $user
     *
     * @return bool
     */
    public function isSamePerson(User $user): bool
    {
        return $this->mainAccountId() === $user->mainAccountId();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return UserQuery
     */
    public function newEloquentBuilder($query): UserQuery
    {
        return new UserQuery($query);
    }
}
