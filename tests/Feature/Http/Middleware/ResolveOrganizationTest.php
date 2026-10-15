<?php

namespace Core\Tests\Feature\Http\Middleware;

use Core\Enums\UserRole;
use Core\Helpers\CurrentOrganization;
use Core\Http\Middleware\ResolveOrganization;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\Traits\BelongsToOrganization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Class ResolveOrganizationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Middleware
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class ResolveOrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of a model of an organization, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $notes;

    /**
     * Organization of the users.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Another organization.
     *
     * @var Organization
     */
    private Organization $globex;

    /**
     * Create the organizations and the notes, and register a route, which tells the current organization, and a
     * route, which binds a note. The latter lists the bindings before authentication, as the `api` group does, so
     * it relies on the middleware's priority.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });

        $notes = $this->notes = get_class(new class extends BaseModel
        {
            use BelongsToOrganization;

            protected $table = 'notes';

            protected $fillable = ['title'];
        });

        $this->acme = Organization::factory()->create();
        $this->globex = Organization::factory()->create();

        Route::middleware(['auth:sanctum', ResolveOrganization::ALIAS])
            ->get('current', fn () => ['organization_id' => CurrentOrganization::id()]);

        Route::bind('note', fn (string $id) => $notes::query()->findOrFail($id));
        Route::middleware([SubstituteBindings::class, 'auth:sanctum', ResolveOrganization::ALIAS])
            ->get('notes/{note}', fn (BaseModel $note) => ['title' => $note->getAttribute('title')]);
    }

    /**
     * Get the current organization of a request, which the user sends with the header, when it is given.
     *
     * @param User $user
     * @param string|null $header
     *
     * @return TestResponse
     */
    private function currentOf(User $user, ?string $header = null): TestResponse
    {
        Sanctum::actingAs($user);

        $headers = $header === null ? [] : [ResolveOrganization::HEADER => $header];

        return $this->getJson('/current', $headers);
    }

    /**
     * Create a super admin, who has no organization.
     *
     * @return User
     */
    private function createSuperAdmin(): User
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findOrCreate(UserRole::SuperAdmin->value));

        return $superAdmin;
    }

    /**
     * Test that a request acts in the organization of the user, who may also name it in the header.
     *
     * @return void
     */
    public function testActsInTheOrganizationOfTheUser(): void
    {
        $user = User::factory()->create(['organization_id' => $this->acme->id]);

        $this->currentOf($user)->assertOk()->assertJsonPath('organization_id', $this->acme->id);
        $this->currentOf($user, (string) $this->acme->id)
            ->assertOk()
            ->assertJsonPath('organization_id', $this->acme->id);
    }

    /**
     * Test that only a super admin acts in another organization, which must exist, with the header.
     *
     * @return void
     */
    public function testOnlySuperAdminActsInAnotherOrganization(): void
    {
        $user = User::factory()->create(['organization_id' => $this->acme->id]);
        $superAdmin = $this->createSuperAdmin();

        $this->currentOf($user, (string) $this->globex->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'Only super admins may act in another organization.');

        $this->currentOf($superAdmin)->assertOk()->assertJsonPath('organization_id', null);
        $this->currentOf($superAdmin, (string) $this->globex->id)
            ->assertOk()
            ->assertJsonPath('organization_id', $this->globex->id);

        $this->currentOf($superAdmin, '999999')->assertNotFound();
        $this->currentOf($superAdmin, 'acme')->assertBadRequest();
    }

    /**
     * Test that route model bindings find the models of the request's organization, as the middleware runs
     * before them, and a model of another organization is not found.
     *
     * @return void
     */
    public function testBindingsFindModelsOfTheOrganization(): void
    {
        $acmeNote = CurrentOrganization::run($this->acme, fn () => $this->notes::query()->create(['title' => 'Acme']));
        $globexNote = CurrentOrganization::run(
            $this->globex,
            fn () => $this->notes::query()->create(['title' => 'Globex'])
        );

        $user = User::factory()->create(['organization_id' => $this->acme->id]);
        $superAdmin = $this->createSuperAdmin();

        Sanctum::actingAs($user);

        $this->getJson('/notes/' . $acmeNote->getKey())->assertOk()->assertJsonPath('title', 'Acme');
        $this->getJson('/notes/' . $globexNote->getKey())->assertNotFound();

        Sanctum::actingAs($superAdmin);

        $this->getJson('/notes/' . $globexNote->getKey(), [ResolveOrganization::HEADER => (string) $this->globex->id])
            ->assertOk()
            ->assertJsonPath('title', 'Globex');
    }
}
