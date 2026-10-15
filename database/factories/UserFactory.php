<?php

namespace Core\Database\Factories;

use Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Class UserFactory.
 *
 * @extends Factory<User>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Factories
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_updated_at' => now(),
        ];
    }

    /**
     * Indicate that the model's password was set by someone else, so the user
     * must set a new one.
     */
    public function requiringPasswordUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_updated_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
