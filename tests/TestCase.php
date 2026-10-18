<?php

namespace Core\Tests;

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
}
