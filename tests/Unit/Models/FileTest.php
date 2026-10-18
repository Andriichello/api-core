<?php

namespace Core\Tests\Unit\Models;

use Core\Models\File;
use Core\Models\User;
use Core\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Class FileTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class FileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The faked files disk.
     *
     * @var FilesystemAdapter
     */
    private FilesystemAdapter $disk;

    /**
     * Fake the files disk.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::fake(config('api-core.files.disk'));

        $this->disk = $disk;
    }

    /**
     * Test that the factory makes a file of a user, which is stored only with the `stored` state.
     *
     * @return void
     */
    public function testFactoryStoresFileOnlyWhenAsked(): void
    {
        $user = User::factory()->create();

        $recorded = File::factory()->for($user, 'fileable')->create();
        $stored = File::factory()->for($user, 'fileable')->stored('Stored contents.')->create();

        $this->assertTrue($user->is($recorded->fileable));
        $this->assertStringStartsWith("files/models.users/{$user->id}/", $recorded->path);
        $this->assertFalse($recorded->isStored());

        $this->assertTrue($stored->isStored());
        $this->assertSame('Stored contents.', $stored->contents());
        $this->assertSame(strlen('Stored contents.'), $stored->size);
        $this->assertSame(hash('sha256', 'Stored contents.'), $stored->sha256);
    }

    /**
     * Test that a file's name is cleaned of slashes and control characters, and kept within 255 characters.
     *
     * @return void
     */
    public function testNameIsCleaned(): void
    {
        $file = new File(['name' => "  ../reports\\2026\x00\x1F.pdf  "]);

        $this->assertSame('..-reports-2026.pdf', $file->name);
        $this->assertSame('file', File::cleanName(" \x07 "));
        $this->assertSame('файл.pdf', File::cleanName('файл.pdf'));
        $this->assertSame(255, mb_strlen(File::cleanName(str_repeat('я', 300))));
    }

    /**
     * Test that the download has the file's contents, type and name, with an ASCII fallback of the name, and
     * forbids sniffing the type.
     *
     * @return void
     */
    public function testDownloadsUnderItsName(): void
    {
        $file = File::factory()->stored('Рахунок.', 'text/plain')->create(['name' => 'Рахунок 1.txt']);

        $response = $file->download();

        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();

        $this->assertSame('Рахунок.', $contents);
        $this->assertSame('text/plain', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame(
            'attachment; filename="Raxunok 1.txt"; filename*=utf-8\'\''
                . '%D0%A0%D0%B0%D1%85%D1%83%D0%BD%D0%BE%D0%BA%201.txt',
            $response->headers->get('Content-Disposition')
        );
    }

    /**
     * Test that a name, of which nothing can be transliterated, falls back to `file` with the extension in ASCII.
     *
     * @return void
     */
    public function testDownloadsNameWithoutAsciiUnderFallbackName(): void
    {
        $file = File::factory()->stored('Smile.', 'text/plain')->create(['name' => '😀😀.txt']);

        $this->assertSame(
            'attachment; filename=file.txt; filename*=utf-8\'\'%F0%9F%98%80%F0%9F%98%80.txt',
            $file->download()->headers->get('Content-Disposition')
        );

        $file->name = '😀';

        $this->assertSame(
            'attachment; filename=file; filename*=utf-8\'\'%F0%9F%98%80',
            $file->contentDisposition()
        );
    }

    /**
     * Test that a file has no URL on a disk, which doesn't provide temporary URLs.
     *
     * @return void
     */
    public function testHasNoUrlOnDiskWithoutTemporaryUrls(): void
    {
        config(['filesystems.disks.plain' => ['driver' => 'local', 'root' => storage_path('framework/testing/plain')]]);

        $file = File::factory()->make(['disk' => 'plain']);

        $this->assertNull($file->url);
    }

    /**
     * Test that a file's URL is a temporary one of its disk, which downloads it under its name.
     *
     * @return void
     */
    public function testUrlIsTemporaryOneOfTheDisk(): void
    {
        $file = File::factory()->stored()->create(['name' => 'act.pdf', 'mime_type' => 'application/pdf']);

        $this->disk->buildTemporaryUrlsUsing(
            function (string $path, DateTimeInterface $expiresAt, array $options) {
                return 'https://files.test/' . $path . '?' . http_build_query($options + [
                    'expires' => $expiresAt->getTimestamp(),
                ]);
            }
        );

        $this->travelTo(now()->startOfSecond());

        $url = $file->url;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://files.test/' . $file->path . '?', $url);
        $this->assertSame('application/pdf', $query['ResponseContentType']);
        $this->assertSame('attachment; filename=act.pdf', $query['ResponseContentDisposition']);
        $this->assertSame(
            (string) now()->addMinutes(config('api-core.files.url_lifetime'))->getTimestamp(),
            $query['expires']
        );
    }
}
