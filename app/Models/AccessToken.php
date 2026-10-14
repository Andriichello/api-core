<?php

namespace Core\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Class AccessToken.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property int|null $impersonator_id
 * @property string $name
 * @property string $token
 * @property string|null $refresh_token
 * @property string[]|null $abilities
 * @property array|null $metadata
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $refresh_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property User|BaseModel $tokenable
 * @property Organization|null $organization
 * @property User|null $impersonator
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
class AccessToken extends PersonalAccessToken
{
    use MassPrunable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens';

    /**
     * The attributes that are mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
        'refresh_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The loadable relationships for the model.
     *
     * @var array
     */
    protected array $relationships = [
        'tokenable',
        'organization',
        'impersonator',
    ];

    /**
     * Organization associated with the model.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * User, who switched to the token's user, if it was issued that way.
     *
     * @return BelongsTo
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * Determines if current token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Determines if the token was issued to a user, to whom another one switched.
     *
     * @return bool
     */
    public function isImpersonated(): bool
    {
        return $this->impersonator_id !== null;
    }

    /**
     * Issue a refresh token for this token, which replaces the one it had. Only
     * its hash is stored, and its plain text is returned once, as `{id}|{secret}`.
     *
     * @param DateTimeInterface $expiresAt
     *
     * @return string
     */
    public function issueRefreshToken(DateTimeInterface $expiresAt): string
    {
        $secret = Str::random(64);

        $this->forceFill([
            'refresh_token' => hash('sha256', $secret),
            'refresh_expires_at' => $expiresAt,
        ])->save();

        return $this->getKey() . '|' . $secret;
    }

    /**
     * Find the token by its refresh token, unless the refresh token has expired.
     *
     * @param string $refreshToken
     *
     * @return static|null
     */
    public static function findByRefreshToken(string $refreshToken): ?static
    {
        [$id, $secret] = array_pad(explode('|', $refreshToken, 2), 2, '');

        if (!ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = static::query()->find((int) $id);

        $isValid = $token instanceof static
            && $token->refresh_token !== null
            && hash_equals($token->refresh_token, hash('sha256', $secret))
            && $token->refresh_expires_at?->isFuture();

        return $isValid ? $token : null;
    }

    /**
     * Get the query of the tokens, which can be pruned: the ones, whose access
     * and refresh tokens have both expired. Sanctum's `sanctum:prune-expired`
     * looks at the access token only, so it would delete valid refresh tokens.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        return static::query()
            ->where('expires_at', '<', now())
            ->where(function (Builder $query) {
                $query->whereNull('refresh_expires_at')
                    ->orWhere('refresh_expires_at', '<', now());
            });
    }
}
