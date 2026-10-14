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
    public function testPublishesConfig(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            CoreServiceProvider::class,
            CoreServiceProvider::CONFIG_TAG
        );

        $this->assertSame(
            [base_path('config/pagination.php') => config_path('pagination.php')],
            $this->resolved($paths)
        );
    }

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

    public function testMergesPaginationConfig(): void
    {
        config(['pagination' => []]);

        (new CoreServiceProvider($this->app))->register();

        $this->assertSame(100, config('pagination.default_size'));
        $this->assertSame('page', config('pagination.pagination_parameter'));
    }

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
