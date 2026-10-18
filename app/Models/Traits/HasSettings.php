<?php

namespace Core\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Interfaces\HasSettingsInterface;
use Core\Models\Setting;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * Trait HasSettings.
 *
 * For models with settings, which implement HasSettingsInterface with it and
 * define their settings in `settingDefinitions()`. A setting's value is read
 * as the stored one, or its default, and is validated by its rules, before
 * it is stored. The stored settings are loaded once, and reloaded after an
 * update. Deleting the model deletes its settings, though a soft-deleted
 * model keeps them, until it is deleted permanently.
 *
 * @mixin BaseModel
 * @property Collection<int, Setting> $settings
 * @see HasSettingsInterface
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Models\Traits
 */
trait HasSettings
{
    /**
     * Boot the trait, registering its model event listeners.
     *
     * @return void
     */
    public static function bootHasSettings(): void
    {
        static::deleted(function (self $model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->settings()->delete();
        });
    }

    /**
     * Stored settings of the model.
     *
     * @return MorphMany
     */
    public function settings(): MorphMany
    {
        return $this->morphMany(Setting::class, 'model');
    }

    /**
     * Get the value of the given setting: the stored one, or its default.
     *
     * @param string $key
     *
     * @return mixed
     * @throws InvalidArgumentException When the setting isn't defined.
     */
    public function setting(string $key): mixed
    {
        $definitions = $this->settingDefinitions();

        if (!array_key_exists($key, $definitions)) {
            throw new InvalidArgumentException("The [$key] setting of " . static::class . " isn't defined.");
        }

        $stored = $this->storedSettings();

        return array_key_exists($key, $stored) ? $stored[$key] : ($definitions[$key]['default'] ?? null);
    }

    /**
     * Get the values of all the model's settings by their keys: the stored
     * ones, or their defaults.
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        $stored = $this->storedSettings();
        $values = [];

        foreach ($this->settingDefinitions() as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : ($definition['default'] ?? null);
        }

        return $values;
    }

    /**
     * Validate the given values of settings, which are keyed by the settings'
     * keys or nested by them, and store them. Values of settings, which
     * aren't defined, fail the validation too.
     *
     * @param array<string, mixed> $values
     *
     * @return void
     * @throws LogicException When the model isn't saved.
     * @throws ValidationException
     */
    public function updateSettings(array $values): void
    {
        if (!$this->exists) {
            throw new LogicException(static::class . ' must be saved, before its settings are updated.');
        }

        $validated = $this->validateSettings($values);

        $this->getConnection()->transaction(function () use ($validated) {
            foreach ($validated as $key => $value) {
                $this->settings()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        $this->unsetRelation('settings');
    }

    /**
     * Reset the given settings, or all of them, when none are given, to their
     * defaults.
     *
     * @param string ...$keys
     *
     * @return void
     */
    public function resetSettings(string ...$keys): void
    {
        $this->settings()
            ->when(!empty($keys), fn ($query) => $query->whereIn('key', $keys))
            ->delete();

        $this->unsetRelation('settings');
    }

    /**
     * Validate the given values of settings with the rules of their
     * definitions, and cast the valid ones.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    protected function validateSettings(array $values): array
    {
        $definitions = $this->settingDefinitions();
        $data = Arr::undot($values);

        $keys = array_values(array_filter(
            array_keys($definitions),
            fn (string $key) => Arr::has($data, $key)
        ));

        $rules = [];

        foreach ($keys as $key) {
            $keyRules = $definitions[$key]['rules'] ?? [];
            $rules[$key] = is_string($keyRules) ? explode('|', $keyRules) : $keyRules;
        }

        $validator = Validator::make($data, $rules);

        $validator->after(function (ValidatorContract $validator) use ($data, $keys) {
            foreach (array_keys(Arr::dot($data)) as $path) {
                if (!$this->isPathOfAnySetting((string) $path, $keys)) {
                    $validator->errors()->add((string) $path, "The $path setting doesn't exist.");
                }
            }
        });

        $validated = $validator->validate();
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->castSetting(Arr::get($validated, $key), $definitions[$key]['cast'] ?? null);
        }

        return $result;
    }

    /**
     * Determine if the given path of a value is one of the given settings, or
     * is within one of them (a setting, whose value is an array).
     *
     * @param string $path
     * @param array<int, string> $keys
     *
     * @return bool
     */
    protected function isPathOfAnySetting(string $path, array $keys): bool
    {
        foreach ($keys as $key) {
            if ($path === $key || str_starts_with($path, $key . '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cast the given value of a setting with the cast of its definition.
     *
     * @param mixed $value
     * @param string|null $cast
     *
     * @return mixed
     * @throws InvalidArgumentException When the cast is unknown.
     */
    protected function castSetting(mixed $value, ?string $cast): mixed
    {
        if ($value === null || $cast === null) {
            return $value;
        }

        return match ($cast) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => (array) $value,
            default => throw new InvalidArgumentException("The [$cast] cast of settings is unknown."),
        };
    }

    /**
     * Get the stored values of the model's settings by their keys, which are
     * loaded once.
     *
     * @return array<string, mixed>
     */
    protected function storedSettings(): array
    {
        if (!$this->exists) {
            return [];
        }

        if (!$this->relationLoaded('settings')) {
            $this->setRelation('settings', $this->settings()->get());
        }

        return $this->getRelation('settings')->pluck('value', 'key')->all();
    }
}
