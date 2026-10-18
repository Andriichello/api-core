<?php

namespace Core\Database\Factories;

use Core\Models\File;
use Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Class FileFactory.
 *
 * Makes the record of a file of a user, which isn't stored on its disk,
 * unless the `stored` state is used. Use `for($model, 'fileable')` to
 * attach it to another model.
 *
 * @extends Factory<File>
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Database\Factories
 */
class FileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<File>
     */
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contents = fake()->paragraph();

        return [
            'fileable_type' => (new User)->getMorphClass(),
            'fileable_id' => User::factory(),
            'collection' => File::DEFAULT_COLLECTION,
            'name' => fake()->word() . '.txt',
            'disk' => config('api-core.files.disk'),
            'path' => fn (array $attributes) => implode('/', [
                config('api-core.files.directory'),
                $attributes['fileable_type'],
                $attributes['fileable_id'],
                Str::uuid() . '.txt',
            ]),
            'mime_type' => 'text/plain',
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }

    /**
     * Indicate that the file is in the given collection.
     */
    public function inCollection(string $collection): static
    {
        return $this->state([
            'collection' => $collection,
        ]);
    }

    /**
     * Indicate that the file is stored on its disk with the given contents.
     */
    public function stored(string $contents = 'Contents of the file.', string $mimeType = 'text/plain'): static
    {
        return $this
            ->state([
                'mime_type' => $mimeType,
                'size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ])
            ->afterMaking(fn (File $file) => $file->storage()->put($file->path, $contents));
    }
}
