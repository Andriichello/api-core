<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Sequence;
use Core\Models\Traits\HasSequences;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Class HasSequencesTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class HasSequencesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the profiles, which own sequences, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $profiles;

    /**
     * Class of the soft-deletable profiles, which are kept in the same table.
     *
     * @var class-string<BaseModel>
     */
    private string $archives;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * Create the table of profiles and their models, which the morph map names.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->profiles = get_class(new class extends BaseModel
        {
            use HasSequences;

            protected $table = 'profiles';

            protected $fillable = ['name'];
        });

        $this->archives = get_class(new class extends BaseModel
        {
            use HasSequences;
            use SoftDeletes;

            protected $table = 'profiles';

            protected $fillable = ['name'];
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
     * @param class-string<BaseModel>|null $class
     *
     * @return BaseModel
     */
    private function createProfile(?string $class = null): BaseModel
    {
        $profile = new ($class ?? $this->profiles)(['name' => 'Profile']);
        $profile->save();

        return $profile;
    }

    /**
     * Test that a model allocates and sets the numbers of its sequences, which are its own.
     *
     * @return void
     */
    public function testAllocatesNumbersOfItsSequences(): void
    {
        $profile = $this->createProfile();

        $hasSequences = method_exists($profile, 'nextInSequence')
            && method_exists($profile, 'setNextInSequence')
            && method_exists($profile, 'sequences');

        if (!$hasSequences) {
            $this->fail('The profiles must have sequences.');
        }

        $this->assertSame(1, $profile->nextInSequence('invoices'));
        $this->assertSame(2, $profile->nextInSequence('invoices'));

        $profile->setNextInSequence('acts', 40);

        $this->assertSame(40, $profile->nextInSequence('acts'));
        $this->assertSame(['acts', 'invoices'], $profile->sequences()->orderBy('key')->pluck('key')->all());
        $this->assertSame('tests.profiles', Sequence::query()->value('model_type'));
    }

    /**
     * Test that deleting a model deletes its sequences, though a soft-deleted one keeps them, until it is deleted
     * permanently.
     *
     * @return void
     */
    public function testDeletingModelDeletesItsSequences(): void
    {
        $profile = $this->createProfile();
        $archive = $this->createProfile($this->archives);

        Sequence::next($profile, 'invoices');
        Sequence::next($archive, 'invoices');

        $profile->delete();
        $archive->delete();

        $this->assertSame(['tests.archives'], Sequence::query()->pluck('model_type')->all());

        $archive->forceDelete();

        $this->assertSame(0, Sequence::query()->count());
    }
}
