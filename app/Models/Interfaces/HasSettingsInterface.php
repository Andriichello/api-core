<?php

namespace Core\Models\Interfaces;

use Core\Models\Traits\HasSettings;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * Interface HasSettingsInterface.
 *
 * @see HasSettings
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Interfaces
 */
interface HasSettingsInterface
{
    /**
     * Get the definitions of the model's settings by their keys, such as
     * `invoice.numbering.strategy`, which nest them in the API. Each one may
     * have a `default` (null otherwise), validation `rules` of its value, and
     * a `cast` (`int`, `float`, `bool`, `string` or `array`), which a valid
     * value is stored as. A key must not be the prefix of another one.
     *
     * @return array<string, array{default?: mixed, rules?: array<int, mixed>|string, cast?: string}>
     */
    public function settingDefinitions(): array;

    /**
     * Stored settings of the model.
     *
     * @return MorphMany
     */
    public function settings(): MorphMany;

    /**
     * Get the value of the given setting: the stored one, or its default.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function setting(string $key): mixed;

    /**
     * Get the values of all the model's settings by their keys: the stored
     * ones, or their defaults.
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array;

    /**
     * Validate the given values of settings, which are keyed by the settings'
     * keys or nested by them, and store them.
     *
     * @param array<string, mixed> $values
     *
     * @return void
     * @throws ValidationException
     */
    public function updateSettings(array $values): void;

    /**
     * Reset the given settings, or all of them, when none are given, to their
     * defaults.
     *
     * @param string ...$keys
     *
     * @return void
     */
    public function resetSettings(string ...$keys): void;
}
