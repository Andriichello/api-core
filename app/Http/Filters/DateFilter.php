<?php

namespace Core\Http\Filters;

use Core\Enums\FilterFlag;
use Core\Enums\FilterOperation;
use Core\Http\Filters\Operations\NullFilter;
use Core\Queries\BaseQuery;
use DateTimeZone;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Class DateFilter.
 *
 * Filters a date or a timestamp column. Its values are validated, so an
 * invalid date or time is answered with 422, and they are read in the
 * user's timezone (`setTimezone()`), or in UTC, unless they give an offset.
 *
 * On a timestamp column, a date without time (`2026-06-30`) means the whole
 * day: `eq` is that day, `gt` is after it, `lte` is up to its end and so on.
 * A date and time is compared exactly. On a date column (a `date` cast),
 * values are dates without time, which are compared as they are given.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Http\Filters
 */
class DateFilter extends BaseFilter
{
    /**
     * Format, in which values are compared to the column.
     *
     * @var string
     */
    protected const string FORMAT = 'Y-m-d H:i:s';

    /**
     * Pattern of a date, with an optional time and an optional offset.
     *
     * @var string
     */
    protected const string PATTERN = '/^(\d{4})-(\d{2})-(\d{2})'
        . '(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?(Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * Operators to their opposites, for the `not` flag.
     *
     * @var array<string, string>
     */
    protected const array OPPOSITES = [
        '=' => '!=',
        '>' => '<=',
        '>=' => '<',
        '<' => '>=',
        '<=' => '>',
    ];

    /**
     * Timezone, in which the values without an offset are read.
     *
     * @var string|null
     */
    protected ?string $timezone = null;

    /**
     * Set the timezone, in which the values without an offset are read.
     * One that is empty or not valid leaves the values in UTC.
     *
     * @param string|null $timezone
     *
     * @return static
     */
    public function setTimezone(?string $timezone): static
    {
        $this->timezone = in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone : null;

        return $this;
    }

    /**
     * Get the timezone, in which the values without an offset are read.
     *
     * @return string
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    /**
     * Apply filter to the given query builder instance.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param mixed $value
     * @param string $boolean
     *
     * @return void
     * @throws InvalidArgumentException
     */
    public function apply(BaseQuery $query, string $column, mixed $value, string $boolean = 'and'): void
    {
        if ($this->operation === FilterOperation::NULL) {
            (new NullFilter($this->table))
                ->setFlags($this->flags)
                ->apply($query, $column, $value, $boolean);

            return;
        }

        if ($this->hasFlag(FilterFlag::CaseInsensitive)) {
            throw new InvalidArgumentException("The 'ci' flag is not supported by date filters.");
        }

        $isDate = $this->isDateColumn($query, $column);
        $column = $this->qualify($column);

        match ($this->operation) {
            FilterOperation::EQ => $this->equals($query, $column, $this->parse($value, $isDate), $boolean),
            FilterOperation::IN => $this->equalsAny($query, $column, $this->parseList($value, $isDate), $boolean),
            FilterOperation::GT,
            FilterOperation::GTE,
            FilterOperation::LT,
            FilterOperation::LTE => $this->compareTo($query, $column, $this->parse($value, $isDate), $boolean),
            default => throw new InvalidArgumentException(
                "The '{$this->operation?->value}' operation is not supported by date filters."
            ),
        };
    }

    /**
     * Add a condition that the column is on the day or at the time.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param array{string, string|null} $value
     * @param string $boolean
     *
     * @return void
     */
    protected function equals(BaseQuery $query, string $column, array $value, string $boolean): void
    {
        [$start, $end] = $value;
        $not = $this->hasFlag(FilterFlag::Not);

        if ($end === null) {
            $query->where($column, $not ? '!=' : '=', $start, $boolean);

            return;
        }

        $query->whereWrapped(
            fn (BaseQuery $query) => $not
                ? $query->where($column, '<', $start)->orWhere($column, '>=', $end)
                : $query->where($column, '>=', $start)->where($column, '<', $end),
            $boolean
        );
    }

    /**
     * Add a condition that the column is on any of the days or at any of
     * the times, or with `not`, on none of them.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param array<int, array{string, string|null}> $values
     * @param string $boolean
     *
     * @return void
     */
    protected function equalsAny(BaseQuery $query, string $column, array $values, string $boolean): void
    {
        $join = $this->hasFlag(FilterFlag::Not) ? 'and' : 'or';

        $query->whereWrapped(function (BaseQuery $query) use ($column, $values, $join) {
            foreach ($values as $value) {
                $this->equals($query, $column, $value, $join);
            }
        }, $boolean);
    }

    /**
     * Add a comparison of the column to the day or the time. Against a whole
     * day, `gt` and `lte` compare to the start of the next day, and `gte` and
     * `lt` to the start of the day itself.
     *
     * @param BaseQuery $query
     * @param string $column
     * @param array{string, string|null} $value
     * @param string $boolean
     *
     * @return void
     */
    protected function compareTo(BaseQuery $query, string $column, array $value, string $boolean): void
    {
        [$start, $end] = $value;

        [$operator, $bound] = match ($this->operation) {
            FilterOperation::GT => $end !== null ? ['>=', $end] : ['>', $start],
            FilterOperation::GTE => ['>=', $start],
            FilterOperation::LT => ['<', $start],
            default => $end !== null ? ['<', $end] : ['<=', $start],
        };

        if ($this->hasFlag(FilterFlag::Not)) {
            $operator = static::OPPOSITES[$operator];
        }

        $query->where($column, $operator, $bound, $boolean);
    }

    /**
     * Parse a list of values, given as a list or comma-separated.
     *
     * @param mixed $value
     * @param bool $isDate
     *
     * @return array<int, array{string, string|null}>
     * @throws InvalidArgumentException
     */
    protected function parseList(mixed $value, bool $isDate): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_map(
            fn (mixed $item) => $this->parse($item, $isDate),
            array_values(Arr::wrap($value))
        );
    }

    /**
     * Parse the value into what the column is compared to: the start and
     * the end (excluded) of a whole day, or a time and no end. On a date
     * column, it is the date itself and no end.
     *
     * @param mixed $value
     * @param bool $isDate
     *
     * @return array{string, string|null}
     * @throws InvalidArgumentException
     */
    protected function parse(mixed $value, bool $isDate): array
    {
        $parts = [];

        if (!is_string($value) || !preg_match(static::PATTERN, trim($value), $parts)) {
            throw $this->invalid($isDate);
        }

        $parts = array_pad($parts, 8, '');
        [, $year, $month, $day, $hour, $minute, $second, $offset] = $parts;

        $hasTime = $hour !== '';
        $isValid = checkdate((int) $month, (int) $day, (int) $year)
            && (int) $hour <= 23 && (int) $minute <= 59 && (int) $second <= 59;

        if (!$isValid || ($isDate && $hasTime)) {
            throw $this->invalid($isDate);
        }

        if ($isDate) {
            return ["$year-$month-$day", null];
        }

        $moment = Carbon::create(
            (int) $year,
            (int) $month,
            (int) $day,
            (int) $hour,
            (int) $minute,
            (int) $second,
            $this->zoneOf($offset)
        );

        $zone = config('app.timezone', 'UTC');

        return $hasTime
            ? [$moment->clone()->setTimezone($zone)->format(static::FORMAT), null]
            : [
                $moment->clone()->setTimezone($zone)->format(static::FORMAT),
                $moment->clone()->addDay()->setTimezone($zone)->format(static::FORMAT),
            ];
    }

    /**
     * Get the timezone of the given offset (`Z`, `+02:00` or `+0200`),
     * or the filter's timezone when there is no offset.
     *
     * @param string $offset
     *
     * @return DateTimeZone
     * @throws InvalidArgumentException
     */
    protected function zoneOf(string $offset): DateTimeZone
    {
        if ($offset === '') {
            return new DateTimeZone($this->getTimezone());
        }

        if ($offset === 'Z') {
            return new DateTimeZone('UTC');
        }

        $hours = (int) substr($offset, 1, 2);
        $minutes = (int) substr($offset, -2);

        if ($hours > 14 || $minutes > 59) {
            throw $this->invalid(false);
        }

        try {
            return new DateTimeZone($offset);
        } catch (Throwable) {
            throw $this->invalid(false);
        }
    }

    /**
     * Determine if the column is a date column (it has a `date` cast)
     * of the query's model, rather than a timestamp one.
     *
     * @param BaseQuery $query
     * @param string $column
     *
     * @return bool
     */
    protected function isDateColumn(BaseQuery $query, string $column): bool
    {
        $model = $query->getModel();
        $prefix = $model->getTable() . '.';

        if (str_starts_with($column, $prefix)) {
            $column = substr($column, strlen($prefix));
        } elseif (str_contains($column, '.')) {
            return false;
        }

        $cast = $model->getCasts()[$column] ?? null;

        return is_string($cast) && preg_match('/^(immutable_)?date(:|$)/', $cast) === 1;
    }

    /**
     * Get the exception for a value, which is not a valid date or time.
     *
     * @param bool $isDate
     *
     * @return InvalidArgumentException
     */
    protected function invalid(bool $isDate): InvalidArgumentException
    {
        return new InvalidArgumentException(
            $isDate
                ? 'A date filter takes a date without time, such as 2026-06-30.'
                : 'A date filter takes a date, or a date and time, such as 2026-06-30 or 2026-06-30T12:00:00Z.'
        );
    }
}
