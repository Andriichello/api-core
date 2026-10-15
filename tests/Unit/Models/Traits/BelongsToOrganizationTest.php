<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Helpers\CurrentOrganization;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\Traits\BelongsToOrganization;
use Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Class BelongsToOrganizationTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class BelongsToOrganizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of a model of an organization, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $notes;

    /**
     * Organization, in which most of the notes are.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Organization, whose notes the others shouldn't see.
     *
     * @var Organization
     */
    private Organization $globex;

    /**
     * Create the table of notes, their model and two organizations.
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

        $this->notes = get_class(new class extends BaseModel
        {
            use BelongsToOrganization;

            protected $table = 'notes';

            protected $fillable = ['title'];
        });

        $this->acme = Organization::factory()->create();
        $this->globex = Organization::factory()->create();
    }

    /**
     * Create a note in the organization.
     *
     * @param Organization $organization
     * @param string $title
     *
     * @return BaseModel
     */
    private function createNote(Organization $organization, string $title): BaseModel
    {
        return CurrentOrganization::run($organization, fn () => $this->notes::query()->create(['title' => $title]));
    }

    /**
     * Test that queries find only the notes of the current organization.
     *
     * @return void
     */
    public function testFindsOnlyModelsOfTheCurrentOrganization(): void
    {
        $this->createNote($this->acme, 'Acme note');
        $globexNote = $this->createNote($this->globex, 'Globex note');

        CurrentOrganization::set($this->acme);

        $this->assertSame(['Acme note'], $this->notes::query()->pluck('title')->all());
        $this->assertNull($this->notes::query()->find($globexNote->getKey()));

        CurrentOrganization::run($this->globex, function () {
            $this->assertSame(['Globex note'], $this->notes::query()->pluck('title')->all());
        });
    }

    /**
     * Test that queries find nothing, when no organization is current, unless the scope is removed explicitly.
     *
     * @return void
     */
    public function testFindsNothingWithoutCurrentOrganization(): void
    {
        $this->createNote($this->acme, 'Acme note');
        $this->createNote($this->globex, 'Globex note');

        $this->assertSame(0, $this->notes::query()->count());
        $this->assertSame(
            2,
            $this->notes::query()->withoutGlobalScope($this->notes::ORGANIZATION_SCOPE)->count()
        );
    }

    /**
     * Test that a new model gets the current organization, and belongs to it.
     *
     * @return void
     */
    public function testNewModelGetsTheCurrentOrganization(): void
    {
        $note = $this->createNote($this->acme, 'Acme note');

        $this->assertSame($this->acme->id, $note->getAttribute('organization_id'));
        $this->assertTrue($this->acme->is($note->getRelationValue('organization')));
    }

    /**
     * Test that a model is saved with the organization, which it is given, when no organization is current (in
     * console or seeders), but not without any.
     *
     * @return void
     */
    public function testWithoutCurrentOrganizationModelNeedsItsOwn(): void
    {
        $note = (new $this->notes)->forceFill(['title' => 'Seeded', 'organization_id' => $this->globex->id]);
        $note->save();

        $this->assertSame($this->globex->id, $note->getAttribute('organization_id'));

        $this->expectException(LogicException::class);

        $this->notes::query()->create(['title' => 'Orphan']);
    }

    /**
     * Test that a model of another organization can't be created or updated in the current one.
     *
     * @return void
     */
    public function testModelOfAnotherOrganizationIsNotSaved(): void
    {
        $globexNote = $this->createNote($this->globex, 'Globex note');

        CurrentOrganization::set($this->acme);

        $failures = 0;

        try {
            (new $this->notes)->forceFill(['title' => 'Moved', 'organization_id' => $this->globex->id])->save();
        } catch (LogicException) {
            $failures++;
        }

        try {
            $globexNote->forceFill(['title' => 'Changed'])->save();
        } catch (LogicException) {
            $failures++;
        }

        $this->assertSame(2, $failures);
        $this->assertSame(
            'Globex note',
            CurrentOrganization::run($this->globex, fn () => $this->notes::query()->value('title'))
        );
    }
}
