<?php

namespace Core\Tests\Feature\Http\Controllers;

use Core\Helpers\CurrentOrganization;
use Core\Http\Controllers\FileController;
use Core\Http\Middleware\ResolveOrganization;
use Core\Http\Requests\Crud\IndexRequest;
use Core\Http\Requests\Crud\ShowRequest;
use Core\Models\BaseModel;
use Core\Models\File;
use Core\Models\Interfaces\HasFilesInterface;
use Core\Models\Organization;
use Core\Models\Traits\BelongsToOrganization;
use Core\Models\Traits\HasFiles;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Repositories\FileRepository;
use Core\Tests\TestCase;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Class FileControllerTest.
 *
 * Files of documents, as in `documents/{document}/files/{id}`, where the documents belong to organizations, and
 * a locked document can be viewed, but not updated.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class FileControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contents of a PDF file, which is recognized as one.
     *
     * @var string
     */
    private const string PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    /**
     * Class of the documents, whose table is created for the tests.
     *
     * @var class-string<BaseModel&HasFilesInterface>
     */
    private string $documents;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * The faked files disk.
     *
     * @var FilesystemAdapter
     */
    private FilesystemAdapter $disk;

    /**
     * Signed-in user.
     *
     * @var User
     */
    private User $user;

    /**
     * Document of the user's organization.
     *
     * @var BaseModel&HasFilesInterface
     */
    private BaseModel&HasFilesInterface $document;

    /**
     * Another document of the user's organization.
     *
     * @var BaseModel&HasFilesInterface
     */
    private BaseModel&HasFilesInterface $other;

    /**
     * Document of the user's organization, which the user can't update.
     *
     * @var BaseModel&HasFilesInterface
     */
    private BaseModel&HasFilesInterface $locked;

    /**
     * Document of another organization.
     *
     * @var BaseModel&HasFilesInterface
     */
    private BaseModel&HasFilesInterface $foreign;

    /**
     * Fake the files disk, create the documents, their controllers and routes, and the documents of two
     * organizations.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::fake(config('api-core.files.disk'));
        $this->disk = $disk;

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('title');
            $table->boolean('locked')->default(false);
            $table->timestamps();
        });

        $this->documents = get_class(new class extends BaseModel implements HasFilesInterface
        {
            use BelongsToOrganization;
            use HasFiles;

            protected $table = 'documents';

            protected $fillable = ['title', 'locked'];

            protected $casts = ['locked' => 'bool'];
        });

        $this->morphMap = Relation::morphMap();
        Relation::morphMap(['tests.documents' => $this->documents]);

        $this->registerRoutes();

        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $this->user = User::factory()->create(['organization_id' => $acme->id]);

        $this->document = $this->createDocument($acme, ['title' => 'Document']);
        $this->other = $this->createDocument($acme, ['title' => 'Other']);
        $this->locked = $this->createDocument($acme, ['title' => 'Locked', 'locked' => true]);
        $this->foreign = $this->createDocument($globex, ['title' => 'Foreign']);
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
     * Create the controllers of the documents' files and register their routes: all operations on all files at
     * `documents/{document}/files`, and only reading the generated ones at `documents/{document}/generated`.
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        // Documents are visible to signed-in users, as their organization's scope allows, and locked ones can't
        // be updated
        $policy = new class extends CrudPolicy
        {
            public function before(mixed $user, string $ability): ?bool
            {
                return $user ? null : false;
            }

            public function update(mixed $user, mixed $model): bool
            {
                return !$model->getAttribute('locked');
            }
        };

        $files = new class($policy, new FileRepository, $this->documents) extends FileController
        {
            protected string $parameter = 'document';

            public function __construct(CrudPolicy $policy, FileRepository $repository, string $fileable)
            {
                parent::__construct($policy, $repository);

                $this->fileable = $fileable;
            }
        };

        $generated = new class($policy, new FileRepository, $this->documents) extends FileController
        {
            protected string $parameter = 'document';

            protected ?string $collection = 'generated';

            protected array $operations = [
                'index' => IndexRequest::class,
                'show' => ShowRequest::class,
                'download' => ShowRequest::class,
            ];

            public function __construct(CrudPolicy $policy, FileRepository $repository, string $fileable)
            {
                parent::__construct($policy, $repository);

                $this->fileable = $fileable;
            }
        };

        foreach (['files' => $files, 'generated' => $generated] as $uri => $controller) {
            $this->app->instance($controller::class, $controller);

            Route::middleware(['auth:sanctum', ResolveOrganization::ALIAS])
                ->group(fn () => $controller::routes("documents/{document}/$uri"));
        }
    }

    /**
     * Create a document with the given attributes in the organization.
     *
     * @param Organization $organization
     * @param array{title: string, locked?: bool} $attributes
     *
     * @return BaseModel&HasFilesInterface
     */
    private function createDocument(Organization $organization, array $attributes): BaseModel&HasFilesInterface
    {
        $document = new $this->documents($attributes);

        CurrentOrganization::run($organization, fn () => $document->save());

        return $document;
    }

    /**
     * Get the URI of the files of the document, or of one of them.
     *
     * @param BaseModel $document
     * @param File|null $file
     * @param string $suffix
     *
     * @return string
     */
    private function uri(BaseModel $document, ?File $file = null, string $suffix = ''): string
    {
        return "documents/{$document->getKey()}/files" . ($file ? "/$file->id" : '') . $suffix;
    }

    /**
     * Test that an uploaded file is attached to the document under its name, and answered without its disk and
     * path.
     *
     * @return void
     */
    public function testUploadsFile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson($this->uri($this->document), [
            'file' => $this->uploadedFile('act.pdf', self::PDF),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'act.pdf')
            ->assertJsonPath('data.collection', File::DEFAULT_COLLECTION)
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.size', strlen(self::PDF))
            ->assertJsonPath('data.fileable_type', 'tests.documents')
            ->assertJsonPath('data.fileable_id', $this->document->getKey())
            ->assertJsonMissingPath('data.disk')
            ->assertJsonMissingPath('data.path');

        $file = File::query()->findOrFail($response->json('data.id'));

        $this->assertSame(self::PDF, $this->disk->get($file->path));
        $this->assertTrue($this->document->is($file->fileable));
    }

    /**
     * Test that an uploaded file is attached under the given name.
     *
     * @return void
     */
    public function testUploadsFileUnderGivenName(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson($this->uri($this->document), [
            'file' => UploadedFile::fake()->createWithContent('scan.pdf', self::PDF),
            'name' => 'Act of 2026/10.pdf',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Act of 2026-10.pdf');
    }

    /**
     * Test that an upload needs a file of an allowed size and type, whose contents are recognized as an allowed
     * type too, and that nothing is stored otherwise.
     *
     * @return void
     */
    public function testUploadIsValidated(): void
    {
        Sanctum::actingAs($this->user);
        config(['api-core.files.max_size' => 1]);

        $uploads = [
            'missing' => null,
            'not a file' => 'act.pdf',
            'too large' => UploadedFile::fake()->createWithContent('large.pdf', self::PDF . str_repeat('0', 1024)),
            'not allowed' => UploadedFile::fake()->createWithContent('page.html', '<html><body>Page</body></html>'),
            'disguised' => $this->uploadedFile('page.pdf', '<html><body>Page</body></html>'),
        ];

        foreach ($uploads as $case => $upload) {
            $response = $this->postJson($this->uri($this->document), array_filter(['file' => $upload]));

            $this->assertSame(422, $response->status(), "The $case upload wasn't rejected.");
            $response->assertJsonValidationErrors('file');
        }

        $this->assertSame([], $this->disk->allFiles());
        $this->assertSame(0, File::query()->count());
    }

    /**
     * Test that the index lists only the document's files, and a controller with a collection only its files.
     *
     * @return void
     */
    public function testListsOnlyFilesOfTheDocument(): void
    {
        $uploaded = $this->document->attachContents('Uploaded.', 'uploaded.txt');
        $generated = $this->document->attachContents('Generated.', 'generated.txt', 'generated');
        $this->other->attachContents('Other.', 'other.txt');

        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->document))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$uploaded->id, $generated->id]);

        $this->getJson("documents/{$this->document->getKey()}/generated")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$generated->id]);

        $this->getJson("documents/{$this->document->getKey()}/generated/$uploaded->id")->assertNotFound();
    }

    /**
     * Test that show, download, rename and delete find only a file of the document.
     *
     * @return void
     */
    public function testFindsOnlyFileOfTheDocument(): void
    {
        $file = $this->other->attachContents('Other.', 'other.txt');

        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->document, $file))->assertNotFound();
        $this->getJson($this->uri($this->document, $file, '/download'))->assertNotFound();
        $this->patchJson($this->uri($this->document, $file), ['name' => 'renamed.txt'])->assertNotFound();
        $this->deleteJson($this->uri($this->document, $file))->assertNotFound();

        $this->getJson($this->uri($this->other, $file))->assertOk()->assertJsonPath('data.name', 'other.txt');
        $this->assertTrue($this->disk->exists($file->path));
    }

    /**
     * Test that the file is downloaded with its contents, type and name, and that a missing stored file is not
     * found.
     *
     * @return void
     */
    public function testDownloadsFile(): void
    {
        $file = $this->document->attachFile(UploadedFile::fake()->createWithContent('act.pdf', self::PDF));

        Sanctum::actingAs($this->user);

        $response = $this->get($this->uri($this->document, $file, '/download'));

        $response->assertOk()
            ->assertDownload('act.pdf')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(self::PDF, $response->streamedContent());

        $this->disk->delete($file->path);

        $this->getJson($this->uri($this->document, $file, '/download'))
            ->assertNotFound()
            ->assertJsonPath('message', 'The stored file is missing.');
    }

    /**
     * Test that a file may append a temporary URL, which the disk provides.
     *
     * @return void
     */
    public function testShowsFileWithUrl(): void
    {
        $file = $this->document->attachContents('Contents.', 'contents.txt');

        $this->disk->buildTemporaryUrlsUsing(
            fn (string $path, DateTimeInterface $expiresAt) => "https://files.test/$path?expires="
                . $expiresAt->getTimestamp()
        );

        Sanctum::actingAs($this->user);

        $url = $this->getJson($this->uri($this->document, $file) . '?append=url')
            ->assertOk()
            ->json('data.url');

        $this->assertStringStartsWith("https://files.test/$file->path?expires=", $url);
    }

    /**
     * Test that a file is renamed, and stays where it is stored.
     *
     * @return void
     */
    public function testRenamesFile(): void
    {
        $file = $this->document->attachContents('Contents.', 'contents.txt');

        Sanctum::actingAs($this->user);

        $this->patchJson($this->uri($this->document, $file), ['name' => 'renamed.txt', 'path' => 'elsewhere.txt'])
            ->assertOk()
            ->assertJsonPath('data.name', 'renamed.txt');

        $this->patchJson($this->uri($this->document, $file), ['collection' => 'generated'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');

        $renamed = $file->fresh();

        $this->assertSame('renamed.txt', $renamed->name);
        $this->assertSame($file->path, $renamed->path);
        $this->assertSame(File::DEFAULT_COLLECTION, $renamed->collection);
    }

    /**
     * Test that a file is deleted along with the stored file.
     *
     * @return void
     */
    public function testDeletesFile(): void
    {
        $file = $this->document->attachContents('Contents.', 'contents.txt');

        Sanctum::actingAs($this->user);

        $this->deleteJson($this->uri($this->document, $file))->assertOk();

        $this->assertModelMissing($file);
        $this->assertFalse($this->disk->exists($file->path));
    }

    /**
     * Test that the files of a document, which the user can't see, aren't found at all.
     *
     * @return void
     */
    public function testDocumentMustBeVisibleToTheUser(): void
    {
        $file = $this->foreign->attachContents('Foreign.', 'foreign.txt');

        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->foreign))->assertNotFound();
        $this->getJson($this->uri($this->foreign, $file, '/download'))->assertNotFound();
        $this->postJson($this->uri($this->foreign), [
            'file' => UploadedFile::fake()->createWithContent('act.pdf', self::PDF),
        ])->assertNotFound();

        $this->assertSame(1, File::query()->count());
    }

    /**
     * Test that the files of a document, which the user can view, but not update, can be listed and downloaded,
     * but not uploaded, renamed or deleted.
     *
     * @return void
     */
    public function testChangingFilesNeedsUpdatingTheDocument(): void
    {
        $file = $this->locked->attachContents('Locked.', 'locked.txt');

        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->locked))->assertOk()->assertJsonPath('data.*.id', [$file->id]);
        $this->getJson($this->uri($this->locked, $file))->assertOk();
        $this->get($this->uri($this->locked, $file, '/download'))->assertOk();

        $this->postJson($this->uri($this->locked), [
            'file' => UploadedFile::fake()->createWithContent('act.pdf', self::PDF),
        ])->assertForbidden();
        $this->patchJson($this->uri($this->locked, $file), ['name' => 'renamed.txt'])->assertForbidden();
        $this->deleteJson($this->uri($this->locked, $file))->assertForbidden();

        $this->assertSame('locked.txt', $file->fresh()->name);
        $this->assertSame(1, File::query()->count());
    }

    /**
     * Test that only the routes of the operations, which a controller handles, are registered.
     *
     * @return void
     */
    public function testRegistersRoutesOfHandledOperationsOnly(): void
    {
        $file = $this->document->attachContents('Generated.', 'generated.txt', 'generated');
        $uri = "documents/{$this->document->getKey()}/generated";

        Sanctum::actingAs($this->user);

        $this->get("$uri/$file->id/download")->assertOk();
        $this->postJson($uri, ['file' => UploadedFile::fake()->createWithContent('act.pdf', self::PDF)])
            ->assertMethodNotAllowed();
        $this->deleteJson("$uri/$file->id")->assertMethodNotAllowed();

        $this->assertTrue($this->disk->exists($file->path));
    }
}
