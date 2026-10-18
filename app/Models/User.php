<?php

namespace Core\Models;

use Core\Database\Factories\UserFactory;
use Core\Helpers\CurrentOrganization;
use Core\Models\Traits\ChecksRolesAndPermissions;
use Core\Models\Traits\LinksAccountsByEmail;
use Core\Models\Traits\SharesPasswordAcrossAccounts;
use Core\Queries\UserQuery;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
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
 * @property Carbon|null $password_updated_at
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
 * @property Collection<int, AccessToken> $tokens
 * @property Collection<int, Role> $roles
 * @property Collection<int, Permission> $permissions
 * @property Collection<int, DatabaseNotification> $notifications
 *
 * @method static UserQuery query()
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(UserFactory::class)]
class User extends BaseModel implements
    AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract,
    MustVerifyEmailContract
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use MustVerifyEmail;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;

    /** @use HasApiTokens<PersonalAccessToken|TransientToken> */
    use HasApiTokens;

    use HasRoles;
    use ChecksRolesAndPermissions;
    use LinksAccountsByEmail;
    use SharesPasswordAcrossAccounts;

    /**
     * Key, under which a session keeps id of the user, who signed in
     * and then switched to another account.
     *
     * @var string
     */
    public const string IMPERSONATOR_SESSION_KEY = 'api-core.impersonator_id';

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
        'password_updated_at',
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
        'password_updated_at' => 'datetime',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // A new email isn't verified, unless it is verified along with the change
            if ($user->exists && $user->isDirty('email') && !$user->isDirty('email_verified_at')) {
                $user->email_verified_at = null;
            }

            // A password counts as updated only when the person sets it, and the code,
            // which lets them, stamps `password_updated_at` along with it, so one set
            // by anyone else (an admin, a seeder) is to be updated
            if ($user->isDirty('password') && !$user->isDirty('password_updated_at')) {
                $user->password_updated_at = null;
            }
        });
    }

    /**
     * Email of the user, which is stored in lowercase, so it is matched
     * regardless of case on every database.
     *
     * @return Attribute
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : Str::lower($value),
        );
    }

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
     * Determine if the user is suspended.
     *
     * @return bool
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Determine if the user can sign in: neither the user nor the organization
     * is suspended, and the organization is neither deactivated nor deleted.
     *
     * @return bool
     */
    public function canSignIn(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        if ($this->organization_id === null) {
            return true;
        }

        $organization = $this->organization;

        return $organization !== null
            && !$organization->isSuspended()
            && !$organization->isDeactivated();
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
     * Get id of the organization, in which the user acts: for a super admin,
     * the current one (see ResolveOrganization), when it is set, and the
     * user's own one otherwise. Other users always act in their own one, so
     * a current organization never widens what they see.
     *
     * @return int|null
     */
    public function actingOrgId(): ?int
    {
        $current = CurrentOrganization::id();

        return $current !== null && $this->isSuperAdmin() ? $current : $this->orgId();
    }

    /**
     * Determine if the user acts in all organizations: a super admin of no
     * organization (an internal one), who doesn't act in a particular one.
     *
     * @return bool
     */
    public function actsInAllOrganizations(): bool
    {
        return $this->isSuperAdmin() && $this->hasInternalOrg() && !CurrentOrganization::isSet();
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
     * Get the password, against which the user is authenticated: the one of
     * the main account, even when it is deleted, as it is the person's
     * password, which all of their accounts share.
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        if ($this->isMainAccount()) {
            return $this->password;
        }

        return static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->whereKey($this->main_account_id)
            ->value('password') ?? $this->password;
    }

    /**
     * Get the time, when the person last set the password: the one of the main
     * account, even when it is deleted, as it is the person's password, which
     * all of their accounts share. It is null, when someone else set it.
     *
     * @return Carbon|null
     */
    public function passwordUpdatedAt(): ?Carbon
    {
        if ($this->isMainAccount()) {
            return $this->password_updated_at;
        }

        /** @var User|null $main */
        $main = static::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->select(['id', 'password_updated_at'])
            ->find($this->main_account_id);

        return $main === null ? $this->password_updated_at : $main->password_updated_at;
    }

    /**
     * Determine if the person must set a new password, because the current
     * one was set by someone else (see RequireUpdatedPassword).
     *
     * @return bool
     */
    public function isPasswordUpdateRequired(): bool
    {
        return $this->passwordUpdatedAt() === null;
    }

    /**
     * Determine if the user is signed in with a session (the SPA's cookie),
     * which Sanctum marks with a transient token, rather than with an
     * access token.
     *
     * @return bool
     */
    public function isSignedInWithSession(): bool
    {
        return $this->currentAccessToken() instanceof TransientToken;
    }

    /**
     * Get the user, who switched to this account: the impersonator recorded
     * on the current access token or, with a session, in the session.
     *
     * @return User|null
     */
    public function currentImpersonator(): ?User
    {
        $token = $this->currentAccessToken();

        if ($token instanceof AccessToken) {
            return $token->isImpersonated() ? $token->impersonator : null;
        }

        $impersonatorId = $this->isSignedInWithSession()
            ? session(static::IMPERSONATOR_SESSION_KEY) : null;

        return $impersonatorId === null ? null : static::query()->find($impersonatorId);
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
