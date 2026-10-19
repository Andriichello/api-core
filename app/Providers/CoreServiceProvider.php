<?php

namespace Core\Providers;

use Core\Http\Middleware\AuditRequests;
use Core\Http\Middleware\ForceJsonOnApi;
use Core\Http\Middleware\RequireUpdatedPassword;
use Core\Http\Middleware\ResolveOrganization;
use Core\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Class CoreServiceProvider.
 *
 * Wires api-core into an application, which requires it as a package. It
 * merges api-core's config and lets the application publish the config and
 * migrations with the `api-core-config` and `api-core-migrations` tags.
 * It also makes the responses on the API paths JSON with ForceJsonOnApi, and
 * registers ResolveOrganization, which sets the request's organization,
 * AuditRequests, which records requests in the audit log, and SetLocale,
 * which sets the request's locale. Outside of debug mode, missing models
 * and enum cases answer a plain "Not found.", which names no class nor id.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
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
        $this->mergeConfigRecursivelyFrom($this->packagePath('config/api-core.php'), 'api-core');
        $this->mergeConfigFrom($this->packagePath('config/pagination.php'), 'pagination');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            $this->packagePath('config/api-core.php') => config_path('api-core.php'),
            $this->packagePath('config/pagination.php') => config_path('pagination.php'),
        ], static::CONFIG_TAG);

        $this->publishes($this->migrationsToPublish(), static::MIGRATIONS_TAG);

        $this->app['router']->aliasMiddleware(ResolveOrganization::ALIAS, ResolveOrganization::class);
        $this->app['router']->aliasMiddleware(AuditRequests::ALIAS, AuditRequests::class);
        $this->app['router']->aliasMiddleware(SetLocale::ALIAS, SetLocale::class);

        $this->callAfterResolving(HttpKernel::class, function (HttpKernel $kernel) {
            if ($kernel instanceof Kernel) {
                // Runs first, so the errors of the other global middleware are JSON too
                $kernel->prependMiddleware(ForceJsonOnApi::class);

                // Runs right after authentication, so route model bindings find
                // the models of the request's organization
                $kernel->addToMiddlewarePriorityAfter(AuthenticatesRequests::class, ResolveOrganization::class);

                // Runs after authentication too, so it knows the user's locale
                $kernel->addToMiddlewarePriorityAfter(ResolveOrganization::class, SetLocale::class);

                // Runs after authentication, wherever a route lists it, or else
                // it finds no user of a token and lets the request through
                $kernel->addToMiddlewarePriorityAfter(SetLocale::class, RequireUpdatedPassword::class);
            }
        });

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler) {
            if ($handler instanceof Handler) {
                $handler->map(ModelNotFoundException::class, $this->hideNotFoundDetails(...));
                $handler->map(BackedEnumCaseNotFoundException::class, $this->hideNotFoundDetails(...));
            }
        });
    }

    /**
     * Turn the exception of a missing model or enum case into a plain 404, which
     * names no class nor id, unless the application is in debug mode. Laravel
     * shows its message ("No query results for model [Core\Models\User] 5")
     * otherwise, even without debug.
     *
     * @param ModelNotFoundException|BackedEnumCaseNotFoundException $exception
     *
     * @return Throwable
     */
    protected function hideNotFoundDetails(
        ModelNotFoundException|BackedEnumCaseNotFoundException $exception
    ): Throwable {
        return config('app.debug') ? $exception : new NotFoundHttpException('Not found.', $exception);
    }

    /**
     * Merge the config file into the application's config under the key. Nested
     * options are merged, so the application can override a single one of them,
     * but lists are replaced as a whole, so the application's list (even an empty
     * one) isn't mixed with api-core's.
     *
     * @param string $path
     * @param string $key
     *
     * @return void
     */
    protected function mergeConfigRecursivelyFrom(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        /** @var array<array-key, mixed> $defaults */
        $defaults = require $path;
        $config = $this->app->make('config');

        $config->set($key, $this->mergeConfig($defaults, $config->get($key, [])));
    }

    /**
     * Merge the application's config into api-core's defaults.
     *
     * @param array<array-key, mixed> $defaults
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    protected function mergeConfig(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            $isNested = is_array($value) && !array_is_list($value)
                && is_array($defaults[$key] ?? null) && !array_is_list($defaults[$key]);

            $defaults[$key] = $isNested ? $this->mergeConfig($defaults[$key], $value) : $value;
        }

        return $defaults;
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
