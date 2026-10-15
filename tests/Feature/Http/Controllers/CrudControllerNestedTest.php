<?php

namespace Core\Tests\Feature\Http\Controllers;

use Core\Http\Controllers\CrudController;
use Core\Http\Requests\Crud\StoreRequest;
use Core\Http\Requests\Crud\UpdateRequest;
use Core\Http\Requests\CrudRequest;
use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Repositories\CrudRepository;
use Core\Tests\TestCase;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Class CrudControllerNestedTest.
 *
 * Notes nested under their organization and their user, as in
 * `organizations/{organization}/users/{user}/notes/{id}`.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.TooManyFields")
 */
class CrudControllerNestedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the notes, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $notes;

    /**
     * Organization of the signed-in user.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Organization, which the signed-in user can't see.
     *
     * @var Organization
     */
    private Organization $globex;

    /**
     * Signed-in user.
     *
     * @var User
     */
    private User $user;

    /**
     * The signed-in user's colleague in the same organization.
     *
     * @var User
     */
    private User $colleague;

    /**
     * The signed-in user's own account in the other organization, which the user sees, but which isn't in the
     * user's organization.
     *
     * @var User
     */
    private User $linked;

    /**
     * Note of the signed-in user.
     *
     * @var BaseModel
     */
    private BaseModel $userNote;

    /**
     * Note of the colleague.
     *
     * @var BaseModel
     */
    private BaseModel $colleagueNote;

    /**
     * Note in the other organization.
     *
     * @var BaseModel
     */
    private BaseModel $globexNote;

    /**
     * Create the notes, their controller, which nests them under the organization and the user, its routes, and
     * the users and notes of two organizations.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });

        $this->notes = get_class(new class extends BaseModel
        {
            protected $table = 'notes';

            protected $fillable = ['title'];

            public function organization(): BelongsTo
            {
                return $this->belongsTo(Organization::class, 'organization_id');
            }

            public function user(): BelongsTo
            {
                return $this->belongsTo(User::class, 'user_id');
            }
        });

        $this->registerRoutes();

        $this->acme = Organization::factory()->create();
        $this->globex = Organization::factory()->create();

        $this->user = User::factory()->create(['organization_id' => $this->acme->id]);
        $this->colleague = User::factory()->create(['organization_id' => $this->acme->id]);
        $this->linked = User::factory()->create([
            'organization_id' => $this->globex->id,
            'main_account_id' => $this->user->id,
        ]);
        $stranger = User::factory()->create(['organization_id' => $this->globex->id]);

        $this->userNote = $this->createNote($this->acme, $this->user, 'User note');
        $this->colleagueNote = $this->createNote($this->acme, $this->colleague, 'Colleague note');
        $this->globexNote = $this->createNote($this->globex, $stranger, 'Globex note');
        $this->createNote($this->globex, $this->linked, 'Linked note');
    }

    /**
     * Create the controller of the notes and register its routes: nested under the organization, under the
     * organization and the user, and, with bindings, under a bound organization.
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        $repository = new class($this->notes) extends CrudRepository
        {
            public function __construct(string $model)
            {
                $this->model = $model;
            }
        };

        $policy = new class extends CrudPolicy
        {
            public function determine(CrudRequest $request): Response|bool
            {
                return true;
            }
        };

        $operations = [
            'store' => get_class(new class extends StoreRequest
            {
                public function rules(): array
                {
                    return ['title' => ['required', 'string']];
                }
            }),
            'update' => get_class(new class extends UpdateRequest
            {
                public function rules(): array
                {
                    return ['title' => ['sometimes', 'string']];
                }
            }),
        ];

        $controller = new class($policy, $repository, $operations) extends CrudController
        {
            protected string $resourceClass = BaseResource::class;

            protected string $collectionClass = BaseCollection::class;

            protected array $parents = ['organization', 'user'];

            public function __construct(CrudPolicy $policy, CrudRepository $repository, array $operations)
            {
                parent::__construct($policy, $repository);

                $this->operations = array_merge($this->operations, $operations);
            }
        };

        $class = get_class($controller);
        $this->app->instance($class, $controller);

        foreach (['organizations/{organization}/notes', 'organizations/{organization}/users/{user}/notes'] as $uri) {
            Route::get($uri, [$class, 'index']);
            Route::post($uri, [$class, 'store']);
            Route::get($uri . '/{id}', [$class, 'show']);
            Route::patch($uri . '/{id}', [$class, 'update']);
            Route::delete($uri . '/{id}', [$class, 'destroy']);
        }

        Route::bind('organization', fn (string $id) => Organization::query()->findOrFail($id));
        Route::get('bound/organizations/{organization}/notes', [$class, 'index'])
            ->middleware(SubstituteBindings::class);
    }

    /**
     * Create a note of the user in the organization.
     *
     * @param Organization $organization
     * @param User $user
     * @param string $title
     *
     * @return BaseModel
     */
    private function createNote(Organization $organization, User $user, string $title): BaseModel
    {
        $note = (new $this->notes)->forceFill([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'title' => $title,
        ]);

        $note->save();

        return $note;
    }

    /**
     * Get the titles of the notes, which the signed-in user lists under the URI.
     *
     * @param string $uri
     *
     * @return array<int, string>
     */
    private function titlesAt(string $uri): array
    {
        return $this->actingAs($this->user)->getJson($uri)->assertOk()->json('data.*.title');
    }

    /**
     * Test that the index lists only the models of the parents, which the route names.
     *
     * @return void
     */
    public function testListsOnlyModelsOfTheParents(): void
    {
        $this->assertEqualsCanonicalizing(
            ['User note', 'Colleague note'],
            $this->titlesAt("organizations/{$this->acme->id}/notes")
        );

        $this->assertSame(
            ['Colleague note'],
            $this->titlesAt("organizations/{$this->acme->id}/users/{$this->colleague->id}/notes")
        );
    }

    /**
     * Test that show, update and destroy find only a model of the parents.
     *
     * @return void
     */
    public function testFindsOnlyModelOfTheParents(): void
    {
        $acme = "organizations/{$this->acme->id}";
        $mine = "$acme/users/{$this->user->id}";

        $this->actingAs($this->user)->getJson("$acme/notes/{$this->userNote->getKey()}")->assertOk();
        $this->getJson("$acme/notes/{$this->globexNote->getKey()}")->assertNotFound();
        $this->getJson("$mine/notes/{$this->colleagueNote->getKey()}")->assertNotFound();
        $this->patchJson("$mine/notes/{$this->colleagueNote->getKey()}", ['title' => 'Changed'])->assertNotFound();
        $this->deleteJson("$mine/notes/{$this->colleagueNote->getKey()}")->assertNotFound();

        $this->assertSame('Colleague note', $this->colleagueNote->fresh()->getAttribute('title'));
    }

    /**
     * Test that a new model belongs to the parents, which the route names, even though their keys aren't
     * mass-assignable, and whatever the input says.
     *
     * @return void
     */
    public function testNewModelBelongsToTheParents(): void
    {
        $this->actingAs($this->user)
            ->postJson("organizations/{$this->acme->id}/users/{$this->colleague->id}/notes", [
                'title' => 'New note',
                'organization_id' => $this->globex->id,
                'user_id' => $this->user->id,
            ])
            ->assertCreated();

        $note = $this->notes::query()->where('title', 'New note')->firstOrFail();

        $this->assertSame($this->acme->id, $note->getAttribute('organization_id'));
        $this->assertSame($this->colleague->id, $note->getAttribute('user_id'));
    }

    /**
     * Test that a parent, which the user can't see, isn't found, whether the route binds it or not.
     *
     * @return void
     */
    public function testParentMustBeVisibleToTheUser(): void
    {
        $this->actingAs($this->user)->getJson("organizations/{$this->globex->id}/notes")->assertNotFound();
        $this->getJson("bound/organizations/{$this->globex->id}/notes")->assertNotFound();

        $this->assertEqualsCanonicalizing(
            ['User note', 'Colleague note'],
            $this->titlesAt("bound/organizations/{$this->acme->id}/notes")
        );
    }

    /**
     * Test that a parent, which the user sees, but which isn't under the outer parent, isn't found, so nothing
     * is listed or created under it.
     *
     * @return void
     */
    public function testParentMustBeUnderTheOuterParent(): void
    {
        $uri = "organizations/{$this->acme->id}/users/{$this->linked->id}/notes";

        $this->actingAs($this->user)->getJson($uri)->assertNotFound();
        $this->postJson($uri, ['title' => 'Misplaced'])->assertNotFound();

        $this->assertFalse($this->notes::query()->where('title', 'Misplaced')->exists());
    }
}
