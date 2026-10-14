<?php

namespace Core\Tests\Unit\Providers;

use Core\Providers\CoreServiceProvider;
use Core\Tests\TestCase;
use Illuminate\Support\ServiceProvider;

/**
 * Class CoreServiceProviderTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Providers
 */
class CoreServiceProviderTest extends TestCase
{
    /**
     * Test that the config files are published under the config tag.
     *
     * @return void
     */
    public function testPublishesConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            CoreServiceProvider::class,
            CoreServiceProvider::CONFIG_TAG
        );

        $this->assertSame(
            [
                base_path('config/api-core.php') => config_path('api-core.php'),
                base_path('config/pagination.php') => config_path('pagination.php'),
            ],
            $this->resolved($paths)
        );
    }

    /**
     * Test that every migration is published under its own name.
     *
     * @return void
     */
    public function testPublishesEveryMigrationUnderItsOwnName(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            CoreServiceProvider::class,
            CoreServiceProvider::MIGRATIONS_TAG
        );

        $migrations = glob(database_path('migrations/*.php'));

        $this->assertNotEmpty($migrations);
        $this->assertSame(
            array_combine($migrations, $migrations),
            $this->resolved($paths)
        );
    }

    /**
     * Test that the pagination config is merged, when the application has none.
     *
     * @return void
     */
    public function testMergesPaginationConfig(): void
    {
        config(['pagination' => []]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame(100, config('pagination.default_size'));
        $this->assertSame('page', config('pagination.pagination_parameter'));
    }

    /**
     * Test that the api-core config is merged recursively, keeping the keys, which the application overrides.
     *
     * @return void
     */
    public function testMergesApiCoreConfig(): void
    {
        config(['api-core' => ['auth' => ['access_token_lifetime' => 60]]]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame(60, config('api-core.auth.access_token_lifetime'));
        $this->assertSame(20160, config('api-core.auth.refresh_token_lifetime'));
    }

    /**
     * Test that a list in the api-core config of the application, even an empty one, replaces api-core's list.
     *
     * @return void
     */
    public function testReplacesListsOfApiCoreConfig(): void
    {
        config(['api-core' => ['http' => ['json_paths' => ['v1/*']]]]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame(['v1/*'], config('api-core.http.json_paths'));
        $this->assertSame(480, config('api-core.auth.access_token_lifetime'));

        config(['api-core' => ['http' => ['json_paths' => []]]]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame([], config('api-core.http.json_paths'));
    }

    /**
     * Test that the pagination config of the application is kept.
     *
     * @return void
     */
    public function testKeepsPaginationConfigOfTheApplication(): void
    {
        config(['pagination.default_size' => 25]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame(25, config('pagination.default_size'));
        $this->assertSame('page', config('pagination.pagination_parameter'));
    }

    /**
     * Resolve the published paths, so they can be compared.
     *
     * @param array<string, string> $paths
     *
     * @return array<string, string>
     */
    private function resolved(array $paths): array
    {
        $resolved = [];

        foreach ($paths as $from => $to) {
            $resolved[realpath($from)] = $to;
        }

        return $resolved;
    }
}
