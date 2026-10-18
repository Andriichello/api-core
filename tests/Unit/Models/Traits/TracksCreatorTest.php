<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\BaseModel;
use Core\Models\Traits\TracksCreator;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Class TracksCreatorTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 */
class TracksCreatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the notes, which track their creator, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $notes;

    /**
     * Create the table of notes, with the creator column the trait recommends, and their model.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->notes = get_class(new class extends BaseModel
        {
            use TracksCreator;

            protected $table = 'notes';

            protected $fillable = ['title'];
        });
    }

    /**
     * Create a note with the given title.
     *
     * @param string $title
     *
     * @return BaseModel
     */
    private function createNote(string $title): BaseModel
    {
        $note = new $this->notes(['title' => $title]);
        $note->save();

        return $note;
    }

    /**
     * Test that a new record gets the signed-in user as its creator, who stays its creator, when another user
     * updates it.
     *
     * @return void
     */
    public function testRecordsSignedInUserAsCreator(): void
    {
        $author = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($author);
        $note = $this->createNote('Note');

        $this->actingAs($editor);
        $note->update(['title' => 'Edited']);

        $fresh = $note->fresh();

        $this->assertSame($author->id, $fresh->getAttribute('created_by_user_id'));
        $this->assertTrue($author->is($fresh->getRelationValue('creator')));
    }

    /**
     * Test that a given creator is kept, and that a record created without a signed-in user has none.
     *
     * @return void
     */
    public function testKeepsGivenCreatorAndHasNoneWithoutUser(): void
    {
        $author = User::factory()->create();

        $note = (new $this->notes(['title' => 'Seeded']))->forceFill(['created_by_user_id' => $author->id]);
        $note->save();

        $this->assertSame($author->id, $note->fresh()->getAttribute('created_by_user_id'));
        $this->assertNull($this->createNote('Console')->fresh()->getAttribute('created_by_user_id'));
    }

    /**
     * Test that the recommended column forgets the creator, when the user is deleted permanently.
     *
     * @return void
     */
    public function testForgetsCreatorDeletedPermanently(): void
    {
        $author = User::factory()->create();

        $this->actingAs($author);
        $note = $this->createNote('Note');

        $author->forceDelete();

        $this->assertNull($note->fresh()->getAttribute('created_by_user_id'));
    }
}
