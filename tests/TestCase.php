<?php

namespace Core\Tests;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

/**
 * Class TestCase.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Make an uploaded file with the given name and contents, whose type is recognized from its contents, unlike
     * the one of `UploadedFile::fake()`, whose type is taken from its name. Its temporary file is deleted after
     * the test.
     *
     * @param string $name
     * @param string $contents
     *
     * @return UploadedFile
     */
    protected function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        $this->beforeApplicationDestroyed(function () use ($path) {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * Get the SQL of the query with its bindings, whose identifiers are quoted with double quotes, as SQLite quotes
     * them, so that assertions on the SQL hold on MySQL too, which quotes them with backticks.
     *
     * @param QueryBuilder|EloquentBuilder $query
     *
     * @return string
     */
    protected function rawSql(QueryBuilder|EloquentBuilder $query): string
    {
        return str_replace('`', '"', $query->toRawSql());
    }

    /**
     * Assert that the arrays have the same keys and values, whatever the order of their keys is at any depth, as
     * JSON columns of MySQL keep no order of keys. The order of lists' items still counts.
     *
     * @param array<array-key, mixed> $expected
     * @param array<array-key, mixed> $actual
     * @param string $message
     *
     * @return void
     */
    protected function assertSameIgnoringKeyOrder(array $expected, array $actual, string $message = ''): void
    {
        $this->assertSame($this->sortedByKeys($expected), $this->sortedByKeys($actual), $message);
    }

    /**
     * Sort the array by its keys at any depth.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    private function sortedByKeys(array $array): array
    {
        ksort($array);

        return array_map(fn (mixed $value) => is_array($value) ? $this->sortedByKeys($value) : $value, $array);
    }
}
