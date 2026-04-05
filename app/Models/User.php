<?php

namespace Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Core\Queries\UserQuery;
use Database\Factories\UserFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Class User.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $timezone
 * @property string $locale
 * @property array|null $metadata
 * @property string|null $email_verified_at
 * @property string|null $suspended_at
 * @property string|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Organization|null $organization
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
    use Authenticatable, Authorizable, CanResetPassword, MustVerifyEmail;
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
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
