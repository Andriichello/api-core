<?php

namespace Core\Providers;

use Core\Models\AccessToken;
use Core\Models\Organization;
use Core\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Class MorphServiceProvider.
 *
 * Important: the morph map is needed to ensure that the
 * models from implementations and `api-core` can be
 * resolved from the same record in the database.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
 */
class MorphServiceProvider extends ServiceProvider
{
    /**
     * Array of models to use in morph map.
     *
     * @var string[]
     */
    protected static array $models = [
        AccessToken::class,
        Organization::class,
        User::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Relation::morphMap(static::getMorphMap());
    }

    /**
     * Get an array of model classes.
     *
     * @return array
     */
    public static function getModelClasses(): array
    {
        return static::$models;
    }

    /**
     * Get morph map for models.
     *
     * @param array|null $models
     *
     * @return array
     */
    public static function getMorphMap(?array $models = null): array
    {
        $morphMap = [];

        foreach ($models ?? static::getModelClasses() as $model) {
            $morphMap[static::slugWithFolderOf($model)] = $model;
        }

        return $morphMap;
    }

    /**
     * Returns the kebab-case slug for a given class or object.
     *
     * @param string|object $class
     * @param bool $plural
     *
     * @return string
     */
    public static function slugOf(string|object $class, bool $plural = true): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        $singular = Str::of($class)
            ->afterLast('\\')
            ->split('/(?=[A-Z])/')
            ->filter()
            ->implode('-');

        $singular = strtolower($singular);

        return $plural ? Str::plural($singular) : $singular;
    }

    /**
     * Returns the kebab-case slug for a given class or object
     * with a folder name prefix, e.g. `folder.class-slug`.
     *
     * @param string|object $class
     * @param bool $plural
     *
     * @return string
     */
    public static function slugWithFolderOf(string|object $class, bool $plural = true): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        $namespace = Str::of($class)
            ->beforeLast('\\')
            ->value();

        $parts = [
            static::slugOf($namespace, false),
            static::slugOf($class, $plural),
        ];

        return implode('.', array_filter($parts));
    }
}
