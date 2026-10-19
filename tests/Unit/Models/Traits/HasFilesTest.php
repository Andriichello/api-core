<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\File;
use Core\Models\Interfaces\HasFilesInterface;
use Core\Models\Traits\HasFiles;
use Core\Tests\TestCase;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

/**
 * Class HasFilesTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class HasFilesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contents of a PDF file, which is recognized as one.
     *
     * @var string
     */
    private const string PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /**
     * Class of the documents, which files are attached to, whose table is created for the tests.
     *
     * @var class-string<BaseModel&HasFilesInterface>
     */
    private string $documents;

    /**
     * Class of the soft-deletable documents, which are kept in the same table.
     *
     * @var class-string<BaseModel&HasFilesInterface>
     */
    private string $archives;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * The faked files disk.
     *
     * @var Filesystem
     */
    private Filesystem $disk;

    /**
     * Fake the files disk, and create the table of documents and their models, which the morph map names. The
     * table is temporary, so that MySQL doesn't commit the test's transaction, as it does on creating a table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake(config('api-core.files.disk'));

        Schema::create('documents', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->documents = get_class(new class extends BaseModel implements HasFilesInterface
        {
            use HasFiles;

            protected $table = 'documents';

            protected $fillable = ['title'];
        });

        $this->archives = get_class(new class extends BaseModel implements HasFilesInterface
        {
            use HasFiles;
            use SoftDeletes;

            protected $table = 'documents';

            protected $fillable = ['title'];
        });

        $this->morphMap = Relation::morphMap();

        Relation::morphMap([
            'tests.documents' => $this->documents,
            'tests.archives' => $this->archives,
        ]);
    }

    /**
     * Restore the morph map.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Relation::morphMap($this->morphMap, false);

        parent::tearDown();
    }

    /**
     * Create a document of the given class.
     *
     * @param class-string<BaseModel&HasFilesInterface>|null $class
     *
     * @return BaseModel&HasFilesInterface
     */
    private function createDocument(?string $class = null): BaseModel&HasFilesInterface
    {
        $document = new ($class ?? $this->documents)(['title' => 'Document']);
        $document->save();

        return $document;
    }

    /**
     * Test that an uploaded file is stored under the model's directory and recorded with the type, size and
     * hash of its contents, under its client name, though the name doesn't tell its type.
     *
     * @return void
     */
    public function testAttachesUploadedFile(): void
    {
        $document = $this->createDocument();

        $file = $document->attachFile($this->uploadedFile('invoice', self::PDF));

        $this->assertTrue($file->exists);
        $this->assertSame('invoice', $file->name);
        $this->assertSame(File::DEFAULT_COLLECTION, $file->collection);
        $this->assertSame(config('api-core.files.disk'), $file->disk);
        $this->assertSame('application/pdf', $file->mime_type);
        $this->assertSame(strlen(self::PDF), $file->size);
        $this->assertSame(hash('sha256', self::PDF), $file->sha256);
        $this->assertNull($file->metadata);
        $this->assertStringStartsWith("files/tests.documents/{$document->getKey()}/", $file->path);
        $this->assertStringEndsWith('.pdf', $file->path);

        $this->assertSame(self::PDF, $this->disk->get($file->path));
        $this->assertTrue($document->is($file->fileable));
        $this->assertSame([$file->id], $document->files()->pluck('id')->all());
        $this->assertSame('tests.documents', $file->fresh()->fileable_type);
    }

    /**
     * Test that contents are attached under the given name, in the given collection, with metadata.
     *
     * @return void
     */
    public function testAttachesContents(): void
    {
        $document = $this->createDocument();

        $file = $document->attachContents('Plain text.', 'notes.txt', 'generated', ['source' => 'test']);

        $this->assertSame('notes.txt', $file->name);
        $this->assertSame('generated', $file->collection);
        $this->assertSame('text/plain', $file->mime_type);
        $this->assertSame(['source' => 'test'], $file->fresh()->metadata);
        $this->assertSame('Plain text.', $file->contents());
        $this->assertStringEndsWith('.txt', $file->path);
    }

    /**
     * Test that a file is attached under the given name, without slashes and control characters, rather than
     * its client name, and that each file gets its own path.
     *
     * @return void
     */
    public function testAttachesFileUnderCleanedName(): void
    {
        $document = $this->createDocument();
        $upload = UploadedFile::fake()->createWithContent('scan.pdf', self::PDF);

        $first = $document->attachFile($upload, "2026/10\\act\x07.pdf");
        $second = $document->attachFile($upload, 'act.pdf');

        $this->assertSame('2026-10-act.pdf', $first->name);
        $this->assertNotSame($first->path, $second->path);
        $this->assertTrue($this->disk->exists($first->path));
        $this->assertTrue($this->disk->exists($second->path));
    }

    /**
     * Test that `filesIn()` gives only the files in the collection.
     *
     * @return void
     */
    public function testFilesInCollection(): void
    {
        $document = $this->createDocument();

        $document->attachContents('Uploaded.', 'uploaded.txt');
        $generated = $document->attachContents('Generated.', 'generated.txt', 'generated');

        $this->assertSame([$generated->id], $document->filesIn('generated')->pluck('id')->all());
        $this->assertCount(2, $document->files()->get());
    }

    /**
     * Test that files are stored under the configured directory.
     *
     * @return void
     */
    public function testFilesDirectoryFollowsConfig(): void
    {
        $document = $this->createDocument();

        $this->assertSame("files/tests.documents/{$document->getKey()}", $document->filesDirectory());

        config(['api-core.files.directory' => '/uploads/']);
        $this->assertSame("uploads/tests.documents/{$document->getKey()}", $document->filesDirectory());

        config(['api-core.files.directory' => '']);
        $this->assertSame("tests.documents/{$document->getKey()}", $document->filesDirectory());
    }

    /**
     * Test that files can't be attached to a model, which isn't saved, and nothing is stored then.
     *
     * @return void
     */
    public function testUnsavedModelCannotHaveFiles(): void
    {
        $document = new $this->documents(['title' => 'Unsaved']);

        try {
            $document->attachContents('Contents.', 'contents.txt');

            $this->fail('A file was attached to an unsaved model.');
        } catch (LogicException) {
            $this->assertSame([], $this->disk->allFiles());
        }
    }

    /**
     * Test that a rollback of the transaction, in which a file is attached, deletes the stored file.
     *
     * @return void
     */
    public function testRollbackDeletesStoredFile(): void
    {
        $document = $this->createDocument();
        $path = null;

        try {
            DB::transaction(function () use ($document, &$path) {
                $path = $document->attachContents('Contents.', 'contents.txt')->path;

                $this->assertTrue($this->disk->exists($path));

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rolled back', $exception->getMessage());
        }

        $this->assertNotNull($path);
        $this->assertFalse($this->disk->exists($path));
        $this->assertSame(0, File::query()->count());
    }

    /**
     * Test that a rollback of a transaction around the one, in which a file is attached, deletes the stored file,
     * even though the inner transaction committed.
     *
     * @return void
     */
    public function testRollbackOfOuterTransactionDeletesStoredFile(): void
    {
        $document = $this->createDocument();
        $path = null;

        try {
            DB::transaction(function () use ($document, &$path) {
                $path = DB::transaction(fn () => $document->attachContents('Contents.', 'contents.txt')->path);

                $this->assertTrue($this->disk->exists($path));

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rolled back', $exception->getMessage());
        }

        $this->assertNotNull($path);
        $this->assertFalse($this->disk->exists($path));
        $this->assertSame(0, File::query()->count());
    }

    /**
     * Test that a rollback of the transaction, in which a file is attached, deletes the stored file, while the
     * transaction around it commits, and the files attached outside of the rollback stay.
     *
     * @return void
     */
    public function testRollbackOfInnerTransactionDeletesOnlyItsStoredFile(): void
    {
        $document = $this->createDocument();
        $kept = null;
        $rolledBack = null;

        DB::transaction(function () use ($document, &$kept, &$rolledBack) {
            $kept = $document->attachContents('Kept.', 'kept.txt')->path;

            try {
                DB::transaction(function () use ($document, &$rolledBack) {
                    $rolledBack = $document->attachContents('Rolled back.', 'rolled-back.txt')->path;

                    throw new RuntimeException('Rolled back');
                });
            } catch (RuntimeException) {
                // The inner transaction is rolled back, while the outer one commits
            }
        });

        $this->assertTrue($this->disk->exists($kept));
        $this->assertFalse($this->disk->exists($rolledBack));
        $this->assertSame([$kept], File::query()->pluck('path')->all());
    }

    /**
     * Test that the stored file is deleted, when recording it fails.
     *
     * @return void
     */
    public function testFailedRecordingDeletesStoredFile(): void
    {
        $document = $this->createDocument();

        File::creating(fn () => throw new RuntimeException('Recording failed'));

        try {
            $document->attachContents('Contents.', 'contents.txt');

            $this->fail('Recording did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Recording failed', $exception->getMessage());
        }

        $this->assertSame([], $this->disk->allFiles());
        $this->assertSame(0, File::query()->count());
    }

    /**
     * Test that the stored file is deleted, and an exception is thrown, when recording it is cancelled.
     *
     * @return void
     */
    public function testCancelledRecordingDeletesStoredFile(): void
    {
        $document = $this->createDocument();

        File::creating(fn () => false);

        try {
            $document->attachContents('Contents.', 'contents.txt');

            $this->fail('Recording was not cancelled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("couldn't be recorded", $exception->getMessage());
        }

        $this->assertSame([], $this->disk->allFiles());
        $this->assertSame(0, File::query()->count());
    }

    /**
     * Test that deleting a file's record deletes the stored file, once the deletion is committed.
     *
     * @return void
     */
    public function testDeletingFileDeletesStoredFileOnCommit(): void
    {
        $file = $this->createDocument()->attachContents('Contents.', 'contents.txt');

        try {
            DB::transaction(function () use ($file) {
                $file->delete();

                $this->assertTrue($this->disk->exists($file->path));

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            $this->assertTrue($this->disk->exists($file->path));
        }

        $this->assertModelExists($file);

        // The rolled back deletion left the instance marked as deleted
        DB::transaction(fn () => File::query()->findOrFail($file->id)->delete());

        $this->assertFalse($this->disk->exists($file->path));
        $this->assertModelMissing($file);
    }

    /**
     * Test that deleting a model deletes its files.
     *
     * @return void
     */
    public function testDeletingModelDeletesItsFiles(): void
    {
        $document = $this->createDocument();
        $first = $document->attachContents('First.', 'first.txt');
        $second = $document->attachContents('Second.', 'second.txt', 'generated');
        $other = $this->createDocument()->attachContents('Other.', 'other.txt');

        $document->delete();

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertFalse($this->disk->exists($first->path));
        $this->assertFalse($this->disk->exists($second->path));

        $this->assertModelExists($other);
        $this->assertTrue($this->disk->exists($other->path));
    }

    /**
     * Test that a soft-deleted model keeps its files, until it is deleted permanently.
     *
     * @return void
     */
    public function testSoftDeletedModelKeepsFilesUntilForceDeleted(): void
    {
        $archive = $this->createDocument($this->archives);
        $file = $archive->attachContents('Contents.', 'contents.txt');

        $archive->delete();

        $this->assertModelExists($file);
        $this->assertTrue($this->disk->exists($file->path));

        $archive->forceDelete();

        $this->assertModelMissing($file);
        $this->assertFalse($this->disk->exists($file->path));
    }
}
