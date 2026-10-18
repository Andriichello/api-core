<?php

namespace Core\Models\Traits;

use BackedEnum;
use Core\Exceptions\StatusTransitionException;
use Core\Models\BaseModel;
use Core\Models\Interfaces\TransitionsStatusInterface;

use function Illuminate\Support\enum_value;

/**
 * Trait TransitionsStatus.
 *
 * For records, whose status changes only along `statusTransitions()`, which
 * implement TransitionsStatusInterface with it, e.g.
 * `['draft' => ['ready'], 'ready' => ['draft', 'sent']]`: changing it to
 * a status, which the current one doesn't lead to, throws
 * StatusTransitionException. A new record may have any status. Statuses are
 * compared by their raw values, so a status with an enum cast may be given
 * as a case.
 *
 * @mixin BaseModel
 * @see TransitionsStatusInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait TransitionsStatus
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootTransitionsStatus(): void
    {
        static::updating(function (self $model) {
            $column = $model->statusColumn();

            if (!$model->isDirty($column)) {
                return;
            }

            $from = $model->rawStatus($model->getRawOriginal($column));
            $to = $model->rawStatus($model->getAttributes()[$column] ?? null);

            if (!$model->leadsTo($from, $to)) {
                throw new StatusTransitionException($model, $from, $to);
            }
        });
    }

    /**
     * Get the statuses, to which each status may change.
     *
     * @return array<string, array<int, BackedEnum|string>>
     */
    abstract public function statusTransitions(): array;

    /**
     * Get the column of the record's status.
     *
     * @return string
     */
    public function statusColumn(): string
    {
        return 'status';
    }

    /**
     * Determine if the record's stored status may change to the given one.
     *
     * @param BackedEnum|string $status
     *
     * @return bool
     */
    public function canTransitionTo(BackedEnum|string $status): bool
    {
        $from = $this->rawStatus($this->getRawOriginal($this->statusColumn()));

        return $from === $this->rawStatus($status) || $this->leadsTo($from, $this->rawStatus($status));
    }

    /**
     * Determine if the one status leads to the other.
     *
     * @param string|null $from
     * @param string|null $to
     *
     * @return bool
     */
    protected function leadsTo(?string $from, ?string $to): bool
    {
        $targets = array_map(
            fn (BackedEnum|string $status) => $this->rawStatus($status),
            $this->statusTransitions()[$from ?? ''] ?? []
        );

        return in_array($to, $targets, true);
    }

    /**
     * Get the raw value of the given status.
     *
     * @param mixed $status
     *
     * @return string|null
     */
    protected function rawStatus(mixed $status): ?string
    {
        $status = enum_value($status);

        return $status === null ? null : (string) $status;
    }
}
