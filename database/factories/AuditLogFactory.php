<?php

namespace Core\Database\Factories;

use Core\Models\AuditLog;
use Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class AuditLogFactory.
 *
 * Makes a manual entry without a causer, subject or organization, which
 * the states give it.
 *
 * @extends Factory<AuditLog>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Factories
 */
class AuditLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AuditLog>
     */
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $event = fake()->randomElement(['exported', 'sent', 'imported']);

        return [
            'source' => AuditLog::SOURCE_MANUAL,
            'event' => $event,
            'name' => $event,
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the given model caused the event.
     */
    public function causedBy(BaseModel $causer): static
    {
        return $this->state([
            'organization_id' => $causer->orgId(),
            'causer_type' => $causer->getMorphClass(),
            'causer_id' => $causer->getKey(),
        ]);
    }

    /**
     * Indicate that the event happened on the given model.
     */
    public function onSubject(BaseModel $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $subject->orgId() ?? $attributes['organization_id'] ?? null,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'name' => $subject->getMorphClass() . '.' . $attributes['event'],
        ]);
    }
}
