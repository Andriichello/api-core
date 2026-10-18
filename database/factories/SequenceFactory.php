<?php

namespace Core\Database\Factories;

use Core\Models\Organization;
use Core\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class SequenceFactory.
 *
 * Makes a sequence of an organization. Use `for($model, 'model')` to make
 * it a sequence of another model.
 *
 * @extends Factory<Sequence>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Factories
 */
class SequenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Sequence>
     */
    protected $model = Sequence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_type' => (new Organization)->getMorphClass(),
            'model_id' => Organization::factory(),
            'key' => fake()->unique()->word(),
            'next_number' => fake()->numberBetween(1, 1000),
        ];
    }
}
