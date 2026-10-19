<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\Sequence;
use Core\Queries\SequenceQuery;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Class SequenceTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class SequenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Owner of the sequences.
     *
     * @var Organization
     */
    private Organization $owner;

    /**
     * Create the owner of the sequences.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = Organization::factory()->create();
    }

    /**
     * Test that a new sequence allocates numbers from 1 in order, kept in a single row.
     *
     * @return void
     */
    public function testAllocatesNumbersInOrder(): void
    {
        $this->assertSame([1, 2, 3], [
            Sequence::next($this->owner, 'invoices'),
            Sequence::next($this->owner, 'invoices'),
            Sequence::next($this->owner, 'invoices'),
        ]);

        $sequence = Sequence::query()->sole();

        $this->assertSame(4, $sequence->next_number);
        $this->assertTrue($this->owner->is($sequence->model));
        $this->assertSame('invoices', $sequence->key);
    }

    /**
     * Test that the sequences of other keys and other owners are separate.
     *
     * @return void
     */
    public function testKeysAndOwnersAreSeparate(): void
    {
        $other = Organization::factory()->create();

        Sequence::next($this->owner, 'invoices');
        Sequence::next($this->owner, 'invoices');

        $this->assertSame(1, Sequence::next($this->owner, 'invoices:2026'));
        $this->assertSame(1, Sequence::next($other, 'invoices'));
        $this->assertSame(3, Sequence::next($this->owner, 'invoices'));
    }

    /**
     * Test that the next number can be set, for a new sequence and an existing one, but not below 1.
     *
     * @return void
     */
    public function testSetsNextNumber(): void
    {
        Sequence::setNext($this->owner, 'invoices', 154);
        $this->assertSame(154, Sequence::next($this->owner, 'invoices'));

        Sequence::setNext($this->owner, 'invoices', 10);
        $this->assertSame(10, Sequence::next($this->owner, 'invoices'));

        $this->expectException(InvalidArgumentException::class);

        Sequence::setNext($this->owner, 'invoices', 0);
    }

    /**
     * Test that peeking tells the next number without allocating it, or creating the sequence.
     *
     * @return void
     */
    public function testPeeksWithoutAllocating(): void
    {
        $this->assertSame(1, Sequence::peek($this->owner, 'invoices'));
        $this->assertSame(0, Sequence::query()->count());

        Sequence::next($this->owner, 'invoices');

        $this->assertSame(2, Sequence::peek($this->owner, 'invoices'));
        $this->assertSame(2, Sequence::query()->sole()->next_number);
        $this->assertSame(2, Sequence::next($this->owner, 'invoices'));
    }

    /**
     * Test that an allocation, whose transaction rolls back, gives its number back, so no number is skipped.
     *
     * @return void
     */
    public function testRolledBackAllocationGivesNumberBack(): void
    {
        Sequence::next($this->owner, 'invoices');

        try {
            DB::transaction(function () {
                $this->assertSame(2, Sequence::next($this->owner, 'invoices'));

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            $this->assertSame(2, Sequence::next($this->owner, 'invoices'));
        }
    }

    /**
     * Test that the row of a sequence is locked for the allocation, so concurrent ones wait for each other. SQLite
     * ignores the lock, so SequenceLockTest proves the waiting on MySQL.
     *
     * @return void
     */
    public function testLocksTheRowOfTheSequence(): void
    {
        $probe = new class extends Sequence
        {
            public static function lockedQueryOf(Model $owner, string $key): SequenceQuery
            {
                return self::lockedQuery($owner, $key);
            }
        };

        $this->assertTrue($probe::lockedQueryOf($this->owner, 'invoices')->getQuery()->lock);
    }

    /**
     * Test that an unsaved owner can't allocate numbers, set them, or peek at them, as it has no sequences.
     *
     * @return void
     */
    public function testUnsavedOwnerCannotAllocate(): void
    {
        $owner = Organization::factory()->make();

        $calls = [
            'next' => fn () => Sequence::next($owner, 'invoices'),
            'setNext' => fn () => Sequence::setNext($owner, 'invoices', 5),
            'peek' => fn () => Sequence::peek($owner, 'invoices'),
        ];

        foreach ($calls as $name => $call) {
            try {
                $call();

                $this->fail("$name accepted an unsaved owner.");
            } catch (LogicException $exception) {
                $this->assertStringContainsString('must be saved', $exception->getMessage());
            }
        }
    }
}
