<?php

namespace Core\Providers;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;

/**
 * Class AppServiceProvider.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->forceHttps();
    }

    /**
     * Force HTTPS on UrlGenerator, hence on the app.
     *
     * @param array $envs
     *
     * @return void
     */
    public function forceHttps(array $envs = [['production', 'staging']]): void
    {
        $env = $this->app->environment();

        if (in_array($env, $envs)) {
            $this->app->afterResolving(
                UrlGenerator::class,
                function ($urlGenerator) {
                    /**
                     * @var UrlGenerator $urlGenerator
                     * @phpstan-ignore-next-line
                     */
                    $urlGenerator->forceScheme('https');
                }
            );
        }
    }
}
