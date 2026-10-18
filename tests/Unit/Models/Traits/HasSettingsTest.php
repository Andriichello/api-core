<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Interfaces\HasSettingsInterface;
use Core\Models\Setting;
use Core\Models\Traits\HasSettings;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * Class HasSettingsTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class HasSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the profiles, which have settings, whose table is created for the tests.
     *
     * @var class-string<BaseModel&HasSettingsInterface>
     */
    private string $profiles;

    /**
     * Class of the soft-deletable profiles, which are kept in the same table.
     *
     * @var class-string<BaseModel&HasSettingsInterface>
     */
    private string $archives;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * Create the table of profiles and their models, which the morph map names, and which define settings:
     * one with a default and allowed values, one, whose rule depends on the profile, a cast one without rules,
     * and an array.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('max_account_id')->default(10);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->profiles = get_class(new class extends BaseModel implements HasSettingsInterface
        {
            use HasSettings;

            protected $table = 'profiles';

            protected $fillable = ['name', 'max_account_id'];

            protected $attributes = ['max_account_id' => 10];

            public function settingDefinitions(): array
            {
                return [
                    'invoice.numbering.strategy' => [
                        'default' => 'date_sequence',
                        'rules' => [Rule::in(['date_sequence', 'sequence'])],
                    ],
                    'taxes.default_account_id' => [
                        'rules' => 'nullable|integer|max:' . $this->getAttribute('max_account_id'),
                        'cast' => 'int',
                    ],
                    'invoice.show_logo' => [
                        'default' => true,
                        'cast' => 'bool',
                    ],
                    'notifications.channels' => [
                        'default' => ['mail'],
                        'rules' => ['array'],
                    ],
                ];
            }
        });

        $this->archives = get_class(new class extends BaseModel implements HasSettingsInterface
        {
            use HasSettings;
            use SoftDeletes;

            protected $table = 'profiles';

            protected $fillable = ['name'];

            public function settingDefinitions(): array
            {
                return ['theme' => ['default' => 'light']];
            }
        });

        $this->morphMap = Relation::morphMap();

        Relation::morphMap([
            'tests.profiles' => $this->profiles,
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
     * Create a profile of the given class.
     *
     * @param class-string<BaseModel&HasSettingsInterface>|null $class
     *
     * @return BaseModel&HasSettingsInterface
     */
    private function createProfile(?string $class = null): BaseModel&HasSettingsInterface
    {
        $profile = new ($class ?? $this->profiles)(['name' => 'Profile']);
        $profile->save();

        return $profile;
    }

    /**
     * Get the validation errors, which updating the profile's settings with the given values gives, by their
     * keys.
     *
     * @param BaseModel&HasSettingsInterface $profile
     * @param array<string, mixed> $values
     *
     * @return array<string, array<int, string>>
     */
    private function errorsOf(BaseModel&HasSettingsInterface $profile, array $values): array
    {
        try {
            $profile->updateSettings($values);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('The settings were updated.');
    }

    /**
     * Test that settings, which aren't stored, have their defaults, or null without one.
     *
     * @return void
     */
    public function testSettingsHaveTheirDefaults(): void
    {
        $profile = $this->createProfile();

        $this->assertSame('date_sequence', $profile->setting('invoice.numbering.strategy'));
        $this->assertNull($profile->setting('taxes.default_account_id'));
        $this->assertSame(
            [
                'invoice.numbering.strategy' => 'date_sequence',
                'taxes.default_account_id' => null,
                'invoice.show_logo' => true,
                'notifications.channels' => ['mail'],
            ],
            $profile->settingValues()
        );
    }

    /**
     * Test that settings are updated by their keys or nested by them, read back, though they were read before,
     * and cast.
     *
     * @return void
     */
    public function testUpdatesSettings(): void
    {
        $profile = $this->createProfile();

        $this->assertSame('date_sequence', $profile->setting('invoice.numbering.strategy'));

        $profile->updateSettings([
            'invoice' => ['numbering' => ['strategy' => 'sequence'], 'show_logo' => '0'],
            'taxes.default_account_id' => '7',
            'notifications.channels' => ['mail', 'sms'],
        ]);

        $this->assertSame('sequence', $profile->setting('invoice.numbering.strategy'));
        $this->assertSame(7, $profile->setting('taxes.default_account_id'));
        $this->assertFalse($profile->setting('invoice.show_logo'));
        $this->assertSame(['mail', 'sms'], $profile->setting('notifications.channels'));

        $this->assertSame($profile->settingValues(), $profile->fresh()->settingValues());
    }

    /**
     * Test that updating a setting again changes its stored value, rather than storing another one, and that
     * a null is stored as given.
     *
     * @return void
     */
    public function testUpdatingAgainChangesStoredValue(): void
    {
        $profile = $this->createProfile();

        $profile->updateSettings(['taxes.default_account_id' => 3]);
        $profile->updateSettings(['taxes.default_account_id' => null, 'invoice.numbering.strategy' => 'sequence']);

        $this->assertNull($profile->setting('taxes.default_account_id'));
        $this->assertSame(2, $profile->settings()->count());
        $this->assertSame(
            ['taxes.default_account_id' => null],
            $profile->settings()->where('key', 'taxes.default_account_id')->pluck('value', 'key')->all()
        );
    }

    /**
     * Test that invalid values, values of settings, which aren't defined, and values within settings, which
     * aren't arrays, fail the validation together, and nothing is stored then.
     *
     * @return void
     */
    public function testInvalidSettingsAreNotStored(): void
    {
        $profile = $this->createProfile();

        $errors = $this->errorsOf($profile, [
            'invoice' => ['numbering' => ['strategy' => 'random', 'scope' => 'global']],
            'taxes.default_account_id' => 11,
            'theme' => 'dark',
        ]);

        $this->assertEqualsCanonicalizing(
            ['invoice.numbering.strategy', 'invoice.numbering.scope', 'taxes.default_account_id', 'theme'],
            array_keys($errors)
        );
        $this->assertSame(["The theme setting doesn't exist."], $errors['theme']);

        $this->assertArrayHasKey('invoice.numbering.strategy', $this->errorsOf($profile, [
            'invoice.numbering.strategy' => ['sequence'],
        ]));

        $this->assertSame(0, $profile->settings()->count());
        $this->assertSame('date_sequence', $profile->setting('invoice.numbering.strategy'));
    }

    /**
     * Test that a setting's rules may depend on the model, as its definitions are its own.
     *
     * @return void
     */
    public function testRulesMayDependOnTheModel(): void
    {
        $profile = $this->createProfile();

        $this->assertArrayHasKey('taxes.default_account_id', $this->errorsOf($profile, [
            'taxes.default_account_id' => 20,
        ]));

        $profile->update(['max_account_id' => 30]);
        $profile->updateSettings(['taxes.default_account_id' => 20]);

        $this->assertSame(20, $profile->setting('taxes.default_account_id'));
    }

    /**
     * Test that reset settings get their defaults again, and that all of them are reset without keys.
     *
     * @return void
     */
    public function testResetsSettingsToTheirDefaults(): void
    {
        $profile = $this->createProfile();

        $profile->updateSettings([
            'invoice.numbering.strategy' => 'sequence',
            'invoice.show_logo' => false,
            'taxes.default_account_id' => 5,
        ]);

        $profile->resetSettings('invoice.numbering.strategy', 'invoice.show_logo');

        $this->assertSame('date_sequence', $profile->setting('invoice.numbering.strategy'));
        $this->assertTrue($profile->setting('invoice.show_logo'));
        $this->assertSame(5, $profile->setting('taxes.default_account_id'));

        $profile->resetSettings();

        $this->assertNull($profile->setting('taxes.default_account_id'));
        $this->assertSame(0, $profile->settings()->count());
    }

    /**
     * Test that stored settings are loaded once, and that a model's settings aren't another one's.
     *
     * @return void
     */
    public function testLoadsStoredSettingsOnceForEachModel(): void
    {
        $profile = $this->createProfile();
        $other = $this->createProfile();

        $profile->updateSettings(['invoice.numbering.strategy' => 'sequence']);

        DB::enableQueryLog();

        $profile->setting('invoice.numbering.strategy');
        $profile->settingValues();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame('date_sequence', $other->setting('invoice.numbering.strategy'));
    }

    /**
     * Test that values of settings, which are no longer defined, are ignored, and that reading a setting, which
     * isn't defined, fails.
     *
     * @return void
     */
    public function testIgnoresSettingsWhichAreNotDefined(): void
    {
        $profile = $this->createProfile();

        Setting::factory()->for($profile, 'model')->create(['key' => 'removed', 'value' => 'old']);

        $this->assertArrayNotHasKey('removed', $profile->settingValues());

        $this->expectException(InvalidArgumentException::class);

        $profile->setting('removed');
    }

    /**
     * Test that an unsaved model has the defaults, but its settings can't be updated.
     *
     * @return void
     */
    public function testUnsavedModelCannotUpdateSettings(): void
    {
        $profile = new $this->profiles(['name' => 'Unsaved']);

        $this->assertSame('date_sequence', $profile->setting('invoice.numbering.strategy'));

        $this->expectException(LogicException::class);

        $profile->updateSettings(['invoice.numbering.strategy' => 'sequence']);
    }

    /**
     * Test that deleting a model deletes its settings, though a soft-deleted one keeps them, until it is deleted
     * permanently.
     *
     * @return void
     */
    public function testDeletingModelDeletesItsSettings(): void
    {
        $profile = $this->createProfile();
        $profile->updateSettings(['invoice.numbering.strategy' => 'sequence']);

        $archive = $this->createProfile($this->archives);
        $archive->updateSettings(['theme' => 'dark']);

        $profile->delete();
        $archive->delete();

        $this->assertSame(['theme'], Setting::query()->pluck('key')->all());

        $archive->forceDelete();

        $this->assertSame(0, Setting::query()->count());
    }
}
