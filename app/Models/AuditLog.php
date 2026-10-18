<?php

namespace Core\Models;

use Core\Database\Factories\AuditLogFactory;
use Core\Helpers\CurrentOrganization;
use Core\Models\Interfaces\HasOrgIdInterface;
use Core\Queries\AuditLogQuery;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as DatabaseBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Class AuditLog.
 *
 * An entry of the audit log: an `event`, which its `causer` (the signed-in
 * user, unless another one is given) caused on its `subject`, in an
 * organization. It comes from a `model` (see Audited), whose `changes` it
 * keeps, from a `request` (see AuditRequests), or from code, which records
 * it `manual`ly with `record()`. Entries can't be changed or deleted one by
 * one, though old ones are pruned (see `api-core.audit.retention_days`).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $source
 * @property string $event
 * @property string $name
 * @property string|null $description
 * @property int|null $status_code
 * @property array{old?: array<string, mixed>, new?: array<string, mixed>}|null $changes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 *
 * @property Organization|null $organization
 * @property BaseModel|null $causer
 * @property BaseModel|null $subject
 *
 * @method static AuditLogQuery query()
 * @phpstan-consistent-constructor
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models
 */
#[UseFactory(AuditLogFactory::class)]
class AuditLog extends BaseModel
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * Source of an entry, which a model's event recorded.
     *
     * @var string
     */
    public const string SOURCE_MODEL = 'model';

    /**
     * Source of an entry, which a request recorded.
     *
     * @var string
     */
    public const string SOURCE_REQUEST = 'request';

    /**
     * Source of an entry, which code recorded.
     *
     * @var string
     */
    public const string SOURCE_MANUAL = 'manual';

    /**
     * The name of the "updated at" column: entries are never updated.
     *
     * @var string|null
     */
    public const ?string UPDATED_AT = null;

    /**
     * The attributes that are mass-assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'causer_type',
        'causer_id',
        'subject_type',
        'subject_id',
        'source',
        'event',
        'name',
        'description',
        'status_code',
        'changes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'organization_id' => 'int',
        'causer_id' => 'int',
        'subject_id' => 'int',
        'status_code' => 'int',
        'changes' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Entries of the audit log can\'t be changed.'));
        static::deleting(fn () => throw new LogicException('Entries of the audit log can\'t be deleted.'));
    }

    /**
     * Record an entry of the given event on the subject. The causer is the
     * signed-in user, unless `causer` is given (null for none), and the
     * organization is the subject's one, the current one, or the causer's
     * one, unless `organization_id` is given. The other attributes default to
     * a manual entry, named `{subject's morph}.{event}`.
     *
     * @param string $event
     * @param Model|null $subject
     * @param array<string, mixed> $attributes
     *
     * @return static
     */
    public static function record(string $event, ?Model $subject = null, array $attributes = []): static
    {
        $causer = array_key_exists('causer', $attributes) ? $attributes['causer'] : Auth::user();
        unset($attributes['causer']);

        $causer = $causer instanceof Model ? $causer : null;

        $entry = new static;

        $entry->forceFill(array_merge([
            'organization_id' => static::organizationIdOf($subject, $causer),
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'source' => static::SOURCE_MANUAL,
            'event' => $event,
            'name' => $subject ? $subject->getMorphClass() . '.' . $event : $event,
        ], $attributes));

        $entry->save();

        return $entry;
    }

    /**
     * Get id of the organization, in which an event on the subject happens:
     * the subject's one, the current one, or the causer's one.
     *
     * @param Model|null $subject
     * @param Model|null $causer
     *
     * @return int|null
     */
    protected static function organizationIdOf(?Model $subject, ?Model $causer): ?int
    {
        $subjectOrgId = $subject instanceof HasOrgIdInterface ? $subject->orgId() : null;

        return $subjectOrgId
            ?? CurrentOrganization::id()
            ?? ($causer instanceof HasOrgIdInterface ? $causer->orgId() : null);
    }

    /**
     * Organization, in which the event happened.
     *
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * User, who caused the event.
     *
     * @return MorphTo
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Model, on which the event happened.
     *
     * @return MorphTo
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Name of the entry, which is cut to the length of its column.
     *
     * @return Attribute
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_substr($value, 0, 191),
        );
    }

    /**
     * Description of the entry, which is cut to the length of its column.
     *
     * @return Attribute
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_substr($value, 0, 255),
        );
    }

    /**
     * Get the query of the entries, which are old enough to be pruned: none,
     * unless `api-core.audit.retention_days` is set.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        $days = config('api-core.audit.retention_days');

        return $days === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('created_at', '<', now()->subDays((int) $days));
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param DatabaseBuilder $query
     *
     * @return AuditLogQuery
     */
    public function newEloquentBuilder($query): AuditLogQuery
    {
        return new AuditLogQuery($query);
    }
}
