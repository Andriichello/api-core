<?php

namespace Database\Factories;

use Core\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Class OrganizationFactory.
 *
 * @extends Factory<Organization>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Database\Factories
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'activated_at' => now(),
        ];
    }

    /**
     * Indicate that the organization is not activated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'activated_at' => null,
        ]);
    }

    /**
     * Indicate that the organization is deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Indicate that the organization is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}
