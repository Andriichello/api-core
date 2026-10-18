<?php

namespace Core\Database\Factories;

use Core\Models\Setting;
use Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class SettingFactory.
 *
 * Makes a stored setting of a user. Use `for($model, 'model')` to make it
 * a setting of another model.
 *
 * @extends Factory<Setting>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Factories
 */
class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Setting>
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_type' => (new User)->getMorphClass(),
            'model_id' => User::factory(),
            'key' => fake()->unique()->word() . '.' . fake()->word(),
            'value' => fake()->word(),
        ];
    }
}
