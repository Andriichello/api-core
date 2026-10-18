<?php

namespace Core\Tests\Feature\Http\Controllers;

use Core\Helpers\CurrentOrganization;
use Core\Http\Controllers\SettingController;
use Core\Http\Middleware\ResolveOrganization;
use Core\Models\BaseModel;
use Core\Models\Interfaces\HasSettingsInterface;
use Core\Models\Organization;
use Core\Models\Traits\BelongsToOrganization;
use Core\Models\Traits\HasSettings;
use Core\Models\User;
use Core\Policies\CrudPolicy;
use Core\Repositories\SettingRepository;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\Sanctum;

/**
 * Class SettingControllerTest.
 *
 * Settings of profiles, as in `profiles/{profile}/settings`, where the profiles belong to organizations, and
 * a locked profile can be viewed, but not updated.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the profiles, whose table is created for the tests.
     *
     * @var class-string<BaseModel&HasSettingsInterface>
     */
    private string $profiles;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * Signed-in user.
     *
     * @var User
     */
    private User $user;

    /**
     * Profile of the user's organization.
     *
     * @var BaseModel&HasSettingsInterface
     */
    private BaseModel&HasSettingsInterface $profile;

    /**
     * Profile of the user's organization, which the user can't update.
     *
     * @var BaseModel&HasSettingsInterface
     */
    private BaseModel&HasSettingsInterface $locked;

    /**
     * Profile of another organization.
     *
     * @var BaseModel&HasSettingsInterface
     */
    private BaseModel&HasSettingsInterface $foreign;

    /**
     * Create the profiles, which define settings, their controller and routes, and the profiles of two
     * organizations.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->timestamps();
        });

        $this->profiles = get_class(new class extends BaseModel implements HasSettingsInterface
        {
            use BelongsToOrganization;
            use HasSettings;

            protected $table = 'profiles';

            protected $fillable = ['name', 'locked'];

            protected $casts = ['locked' => 'bool'];

            public function settingDefinitions(): array
            {
                return [
                    'invoice.numbering.strategy' => [
                        'default' => 'date_sequence',
                        'rules' => [Rule::in(['date_sequence', 'sequence'])],
                    ],
                    'invoice.numbering.reset' => [
                        'default' => 'never',
                        'rules' => [Rule::in(['never', 'yearly'])],
                    ],
                    'taxes.default_account_id' => [
                        'rules' => ['nullable', 'integer'],
                        'cast' => 'int',
                    ],
                ];
            }
        });

        $this->morphMap = Relation::morphMap();
        Relation::morphMap(['tests.profiles' => $this->profiles]);

        $this->registerRoutes();

        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $this->user = User::factory()->create(['organization_id' => $acme->id]);

        $this->profile = $this->createProfile($acme, ['name' => 'Profile']);
        $this->locked = $this->createProfile($acme, ['name' => 'Locked', 'locked' => true]);
        $this->foreign = $this->createProfile($globex, ['name' => 'Foreign']);
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
     * Create the controller of the profiles' settings and register its routes at `profiles/{profile}/settings`.
     *
     * @return void
     */
    private function registerRoutes(): void
    {
        // Profiles are visible to signed-in users, as their organization's scope allows, and locked ones can't
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

        $controller = new class($policy, new SettingRepository, $this->profiles) extends SettingController
        {
            protected string $parameter = 'profile';

            public function __construct(CrudPolicy $policy, SettingRepository $repository, string $settable)
            {
                parent::__construct($policy, $repository);

                $this->settable = $settable;
            }
        };

        $this->app->instance($controller::class, $controller);

        Route::middleware(['auth:sanctum', ResolveOrganization::ALIAS])
            ->group(fn () => $controller::routes('profiles/{profile}/settings'));
    }

    /**
     * Create a profile with the given attributes in the organization.
     *
     * @param Organization $organization
     * @param array{name: string, locked?: bool} $attributes
     *
     * @return BaseModel&HasSettingsInterface
     */
    private function createProfile(Organization $organization, array $attributes): BaseModel&HasSettingsInterface
    {
        $profile = new $this->profiles($attributes);

        CurrentOrganization::run($organization, fn () => $profile->save());

        return $profile;
    }

    /**
     * Get the URI of the profile's settings.
     *
     * @param BaseModel $profile
     *
     * @return string
     */
    private function uri(BaseModel $profile): string
    {
        return "profiles/{$profile->getKey()}/settings";
    }

    /**
     * Test that all settings are shown, nested by their keys, with the defaults of those, which aren't stored.
     *
     * @return void
     */
    public function testShowsSettingsWithDefaults(): void
    {
        $this->profile->updateSettings(['invoice.numbering.strategy' => 'sequence']);

        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->profile))
            ->assertOk()
            ->assertExactJson([
                'message' => 'OK',
                'data' => [
                    'invoice' => ['numbering' => ['strategy' => 'sequence', 'reset' => 'never']],
                    'taxes' => ['default_account_id' => null],
                ],
            ]);
    }

    /**
     * Test that the given settings are updated, and all of them are answered.
     *
     * @return void
     */
    public function testUpdatesSettings(): void
    {
        Sanctum::actingAs($this->user);

        $this->patchJson($this->uri($this->profile), [
            'invoice' => ['numbering' => ['reset' => 'yearly']],
            'taxes' => ['default_account_id' => '4'],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Updated')
            ->assertJsonPath('data.invoice.numbering', ['strategy' => 'date_sequence', 'reset' => 'yearly'])
            ->assertJsonPath('data.taxes.default_account_id', 4);

        // The profile's stored settings weren't loaded yet, so they are read from the database
        $this->assertSame('yearly', $this->profile->setting('invoice.numbering.reset'));
        $this->assertSame(4, $this->profile->setting('taxes.default_account_id'));
    }

    /**
     * Test that invalid settings, settings, which aren't defined, and an empty or a list body are rejected, and
     * that nothing is stored then.
     *
     * @return void
     */
    public function testRejectsInvalidSettings(): void
    {
        Sanctum::actingAs($this->user);

        $this->patchJson($this->uri($this->profile), [
            'invoice' => ['numbering' => ['strategy' => 'random']],
            'theme' => 'dark',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice.numbering.strategy', 'theme']);

        $this->patchJson($this->uri($this->profile), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->patchJson($this->uri($this->profile), ['sequence', 'yearly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, $this->profile->settings()->count());
    }

    /**
     * Test that parameters of the query aren't taken for settings.
     *
     * @return void
     */
    public function testQueryIsNotTakenForSettings(): void
    {
        Sanctum::actingAs($this->user);

        $this->patchJson($this->uri($this->profile) . '?theme=dark', ['invoice.numbering.reset' => 'yearly'])
            ->assertOk()
            ->assertJsonPath('data.invoice.numbering.reset', 'yearly');
    }

    /**
     * Test that the settings of a profile, which the user can't see, aren't found at all.
     *
     * @return void
     */
    public function testProfileMustBeVisibleToTheUser(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->foreign))->assertNotFound();
        $this->patchJson($this->uri($this->foreign), ['invoice.numbering.reset' => 'yearly'])->assertNotFound();

        $this->assertSame(0, $this->foreign->settings()->count());
    }

    /**
     * Test that the settings of a profile, which the user can view, but not update, can be seen, but not changed.
     *
     * @return void
     */
    public function testChangingSettingsNeedsUpdatingTheProfile(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson($this->uri($this->locked))->assertOk();
        $this->patchJson($this->uri($this->locked), ['invoice.numbering.reset' => 'yearly'])->assertForbidden();

        $this->assertSame(0, $this->locked->settings()->count());
    }
}
