<?php

namespace Core\Models\Traits;

use Core\Models\AuditLog;
use Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait Audited.
 *
 * For models, whose changes the audit log keeps: an entry is recorded, when
 * the model is `created`, `updated`, `deleted` (softly, if it can be),
 * `restored` or `force_deleted`, with the attributes' raw values: the `new`
 * ones of a created model, the `old` and `new` ones of the changed attributes
 * of an updated one, and the `old` ones of a deleted one. Values of hidden
 * attributes are masked, and the key, timestamps and `auditExcluded()`
 * attributes are left out. An update, which changes nothing else, isn't
 * recorded. The entries are written with the model, so a rolled back change
 * leaves none.
 *
 * @mixin BaseModel
 * @property Collection<int, AuditLog> $auditLogs
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait Audited
{
    /**
     * Value, which a hidden attribute's value is recorded as.
     *
     * @var string
     */
    public const string HIDDEN_VALUE = '[hidden]';

    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootAudited(): void
    {
        static::created(fn (self $model) => $model->recordAudit('created'));
        static::updated(fn (self $model) => $model->recordAudit('updated'));
        static::deleted(fn (self $model) => $model->recordAudit(
            method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? 'force_deleted' : 'deleted'
        ));
    }

    /**
     * Entries of the audit log, whose subject the model is.
     *
     * @return MorphMany
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject');
    }

    /**
     * Get the attributes, which the audit log leaves out, besides the key and
     * the timestamps.
     *
     * @return array<int, string>
     */
    public function auditExcluded(): array
    {
        return [];
    }

    /**
     * Record an entry of the given event of the model in the audit log, unless
     * it is an update, which changes nothing, that is audited. An update, which
     * restores a soft-deleted model, is recorded as `restored`.
     *
     * @param string $event
     *
     * @return void
     */
    protected function recordAudit(string $event): void
    {
        if ($event === 'updated' && $this->isBeingRestored()) {
            $event = 'restored';
        }

        $changes = $this->auditChanges($event);

        if ($event === 'updated' && empty($changes)) {
            return;
        }

        AuditLog::record($event, $this, [
            'source' => AuditLog::SOURCE_MODEL,
            'changes' => empty($changes) ? null : $changes,
        ]);
    }

    /**
     * Get the audited changes, which the given event made: the `old` and the
     * `new` values of the attributes.
     *
     * @param string $event
     *
     * @return array{old?: array<string, mixed>, new?: array<string, mixed>}
     */
    protected function auditChanges(string $event): array
    {
        $attributes = $this->auditedValues($this->getAttributes());

        if ($event === 'created') {
            return empty($attributes) ? [] : ['new' => $attributes];
        }

        if ($event === 'deleted' || $event === 'force_deleted') {
            return empty($attributes) ? [] : ['old' => $attributes];
        }

        $new = $this->auditedValues($this->getChanges());

        if (empty($new)) {
            return [];
        }

        $old = $this->auditedValues(array_intersect_key($this->getRawOriginal(), $new));

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Leave the attributes, which aren't audited, out of the given values,
     * and mask the hidden ones.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function auditedValues(array $values): array
    {
        $excluded = array_merge(
            [$this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn()],
            method_exists($this, 'getDeletedAtColumn') ? [$this->getDeletedAtColumn()] : [],
            $this->auditExcluded(),
        );

        $values = array_diff_key($values, array_flip(array_filter($excluded)));

        foreach (array_intersect(array_keys($values), $this->getHidden()) as $hidden) {
            $values[$hidden] = static::HIDDEN_VALUE;
        }

        return $values;
    }

    /**
     * Determine if the model's update is restoring it from a soft delete.
     *
     * @return bool
     */
    protected function isBeingRestored(): bool
    {
        if (!method_exists($this, 'getDeletedAtColumn')) {
            return false;
        }

        $column = $this->getDeletedAtColumn();

        return $this->wasChanged($column)
            && $this->getAttribute($column) === null
            && $this->getRawOriginal($column) !== null;
    }
}
