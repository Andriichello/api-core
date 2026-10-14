<?php

namespace Core\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class CoreServiceProvider.
 *
 * Wires api-core into an application, which requires it as a package. It
 * merges api-core's config and lets the application publish the config and
 * migrations with the `api-core-config` and `api-core-migrations` tags.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Tag, with which api-core's config is published.
     *
     * @var string
     */
    public const string CONFIG_TAG = 'api-core-config';

    /**
     * Tag, with which api-core's migrations are published.
     *
     * @var string
     */
    public const string MIGRATIONS_TAG = 'api-core-migrations';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/pagination.php'), 'pagination');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            $this->packagePath('config/pagination.php') => config_path('pagination.php'),
        ], static::CONFIG_TAG);

        $this->publishes($this->migrationsToPublish(), static::MIGRATIONS_TAG);
    }

    /**
     * Get api-core's migrations mapped to the application's migrations. They
     * keep their names, so publishing again copies only the ones added since.
     *
     * @return array<string, string>
     */
    protected function migrationsToPublish(): array
    {
        $migrations = [];

        foreach (glob($this->packagePath('database/migrations/*.php')) as $path) {
            $migrations[$path] = database_path('migrations/' . basename($path));
        }

        return $migrations;
    }

    /**
     * Get the path to a file of api-core.
     *
     * @param string $path
     *
     * @return string
     */
    protected function packagePath(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . $path;
    }
}
