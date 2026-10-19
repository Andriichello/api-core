<?php

namespace Core\Tests\Unit\Models;

use Core\Models\Organization;
use Core\Models\Sequence;
use Core\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Class SequenceLockTest.
 *
 * Allocates numbers of a sequence through two connections, while the first one holds the sequence's row lock in
 * an open transaction. SQLite ignores row locks, so the tests are skipped on it; run them on MySQL with
 * `DB_CONNECTION=mysql DB_DATABASE=api_core_test php artisan test tests/Unit/Models/SequenceLockTest.php`.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class SequenceLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Key of the sequence.
     *
     * @var string
     */
    private const string KEY = 'invoices';

    /**
     * Connections, which allocate the numbers, outside the test's transaction, so that they commit.
     *
     * @var string[]
     */
    private const array CONNECTIONS = ['first', 'second'];

    /**
     * Owner of the sequence.
     *
     * @var Organization
     */
    private Organization $owner;

    /**
     * Skip the tests on databases other than MySQL, create the owner, and make the connections, the second of
     * which waits for a row lock no longer than a second.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('SQLite ignores row locks, so they are tested on MySQL only.');
        }

        $this->owner = Organization::factory()->create();

        foreach (self::CONNECTIONS as $name) {
            config(["database.connections.$name" => config('database.connections.' . DB::getDefaultConnection())]);
        }

        DB::connection('second')->statement('set session innodb_lock_wait_timeout = 1');

        $this->beforeApplicationDestroyed(fn () => $this->cleanUpConnections());
    }

    /**
     * Test that an allocation waits for the row lock, which another allocation holds until its transaction
     * commits, and then allocates the number after the committed one.
     *
     * @return void
     */
    public function testAllocationWaitsForTheLockOfAnotherOne(): void
    {
        $this->assertSame(1, $this->allocateThrough('first'));

        DB::connection('first')->beginTransaction();
        $this->assertSame(2, $this->allocateThrough('first'));

        $this->assertWaitsForTheLock('second');

        DB::connection('first')->commit();

        $this->assertSame(3, $this->allocateThrough('second'));
    }

    /**
     * Test that an allocation waits for the row of a new sequence, which another allocation created, until its
     * transaction commits, and then continues the sequence in the same row.
     *
     * @return void
     */
    public function testAllocationWaitsForTheRowCreatedByAnotherOne(): void
    {
        DB::connection('first')->beginTransaction();
        $this->assertSame(1, $this->allocateThrough('first'));

        $this->assertWaitsForTheLock('second');

        DB::connection('first')->commit();

        $this->assertSame(2, $this->allocateThrough('second'));
        $this->assertSame(3, (int) $this->sequencesOfOwner('second')->sole()->next_number);
    }

    /**
     * Allocate the next number of the owner's sequence through the connection, as a request, whose default
     * connection it is, would.
     *
     * @param string $connection
     *
     * @return int
     */
    private function allocateThrough(string $connection): int
    {
        $default = DB::getDefaultConnection();

        DB::setDefaultConnection($connection);

        try {
            return Sequence::next($this->owner, self::KEY);
        } finally {
            DB::setDefaultConnection($default);
        }
    }

    /**
     * Assert that an allocation through the connection waits for the row lock, before it reads the next number,
     * until the wait times out on every attempt. Without the lock, it would read the number, which the other
     * allocation takes, and wait only to write it, so it would allocate that number again after the other commits.
     *
     * @param string $connection
     *
     * @return void
     */
    private function assertWaitsForTheLock(string $connection): void
    {
        try {
            $number = $this->allocateThrough($connection);

            $this->fail("The allocation didn't wait for the row lock and allocated $number.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Lock wait timeout exceeded', $exception->getMessage());
            $this->assertStringStartsWith('select', $exception->getSql(), 'It waited to write, not to read.');
            $this->assertStringEndsWith('for update', $exception->getSql());
        }
    }

    /**
     * Get the query of the owner's sequences through the connection.
     *
     * @param string $connection
     *
     * @return Builder
     */
    private function sequencesOfOwner(string $connection): Builder
    {
        return DB::connection($connection)
            ->table('sequences')
            ->where('model_type', $this->owner->getMorphClass())
            ->where('model_id', $this->owner->getKey());
    }

    /**
     * Roll back the transactions, which the connections left open, delete the sequences, which they committed,
     * and disconnect them.
     *
     * @return void
     */
    private function cleanUpConnections(): void
    {
        foreach (self::CONNECTIONS as $name) {
            DB::connection($name)->rollBack(0);
        }

        $this->sequencesOfOwner('first')->delete();

        foreach (self::CONNECTIONS as $name) {
            DB::purge($name);
        }
    }
}
