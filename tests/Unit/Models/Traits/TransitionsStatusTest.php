<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Enums\UserRole;
use Core\Exceptions\RecordLockedException;
use Core\Exceptions\StatusTransitionException;
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Core\Models\Interfaces\TransitionsStatusInterface;
use Core\Models\Traits\Lockable;
use Core\Models\Traits\TransitionsStatus;
use Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Class TransitionsStatusTest.
 *
 * Invoices going `draft ⇄ ready → sent → paid`, with `ready` and `sent` able to be `cancelled`, which are locked past
 * their draft, and members, whose `level` is an enum, going up from a guest to an admin.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class TransitionsStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the invoices, whose table is created for the tests.
     *
     * @var class-string<BaseModel&LockableInterface&TransitionsStatusInterface>
     */
    private string $invoices;

    /**
     * Class of the members, whose table is created for the tests.
     *
     * @var class-string<BaseModel&TransitionsStatusInterface>
     */
    private string $members;

    /**
     * Create the tables of invoices and members, and their models.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('level');
            $table->timestamps();
        });

        $this->invoices = get_class(new class extends BaseModel implements LockableInterface, TransitionsStatusInterface
        {
            use Lockable;
            use TransitionsStatus;

            protected $table = 'invoices';

            protected $fillable = ['status', 'total'];

            public function statusTransitions(): array
            {
                return [
                    'draft' => ['ready'],
                    'ready' => ['draft', 'sent', 'cancelled'],
                    'sent' => ['paid', 'cancelled'],
                ];
            }

            public function isLocked(): bool
            {
                return $this->getAttribute('status') !== 'draft';
            }

            public function unlockedAttributes(): array
            {
                return ['status'];
            }
        });

        $this->members = get_class(new class extends BaseModel implements TransitionsStatusInterface
        {
            use TransitionsStatus;

            protected $table = 'members';

            protected $fillable = ['level'];

            protected $casts = ['level' => UserRole::class];

            public function statusColumn(): string
            {
                return 'level';
            }

            public function statusTransitions(): array
            {
                return [
                    UserRole::Guest->value => [UserRole::User],
                    UserRole::User->value => [UserRole::Admin, 'guest'],
                ];
            }
        });
    }

    /**
     * Create an invoice with the given status.
     *
     * @param string $status
     *
     * @return BaseModel&LockableInterface&TransitionsStatusInterface
     */
    private function createInvoice(string $status): BaseModel&LockableInterface&TransitionsStatusInterface
    {
        $invoice = new $this->invoices(['status' => $status, 'total' => 100]);
        $invoice->save();

        return $invoice;
    }

    /**
     * Get the exception, which the given callback throws, as the status can't change so.
     *
     * @param callable $callback
     *
     * @return StatusTransitionException
     */
    private function rejectedBy(callable $callback): StatusTransitionException
    {
        try {
            $callback();
        } catch (StatusTransitionException $exception) {
            return $exception;
        }

        $this->fail('The status changed.');
    }

    /**
     * Test that the status changes along the transitions, and a new record may have any status.
     *
     * @return void
     */
    public function testChangesAlongTheTransitions(): void
    {
        $invoice = $this->createInvoice('draft');

        foreach (['ready', 'draft', 'ready', 'sent', 'paid'] as $status) {
            $invoice->update(['status' => $status]);
        }

        $this->assertSame('paid', $invoice->fresh()->getAttribute('status'));
        $this->assertSame('cancelled', $this->createInvoice('cancelled')->fresh()->getAttribute('status'));
    }

    /**
     * Test that the status doesn't change to one, which the current one doesn't lead to, including from a status,
     * which leads nowhere.
     *
     * @return void
     */
    public function testRejectsOtherChanges(): void
    {
        $sent = $this->createInvoice('sent');

        $exception = $this->rejectedBy(fn () => $sent->update(['status' => 'draft']));

        $this->assertSame('sent', $exception->from);
        $this->assertSame('draft', $exception->to);
        $this->assertSame("The status can't change from sent to draft.", $exception->getMessage());
        $this->assertTrue($sent->is($exception->record));
        $this->assertSame('sent', $sent->fresh()->getAttribute('status'));

        $paid = $this->createInvoice('paid');
        $this->rejectedBy(fn () => $paid->update(['status' => 'cancelled']));
    }

    /**
     * Test that an update, which keeps the status, isn't checked.
     *
     * @return void
     */
    public function testKeepingTheStatusIsNotChecked(): void
    {
        $invoice = $this->createInvoice('paid');

        $invoice->update(['status' => 'paid']);

        $this->travel(1)->minutes();
        $invoice->touch();

        $this->assertSame('paid', $invoice->fresh()->getAttribute('status'));
    }

    /**
     * Test that a record tells the statuses, which its stored one may change to.
     *
     * @return void
     */
    public function testTellsWhetherItCanTransition(): void
    {
        $invoice = $this->createInvoice('ready');
        $invoice->setAttribute('status', 'sent');

        $this->assertTrue($invoice->canTransitionTo('draft'));
        $this->assertTrue($invoice->canTransitionTo('ready'));
        $this->assertFalse($invoice->canTransitionTo('paid'));
    }

    /**
     * Test that statuses with an enum cast are compared by their values, whether the transitions name cases or
     * values.
     *
     * @return void
     */
    public function testComparesEnumStatusesByValues(): void
    {
        $member = new $this->members(['level' => UserRole::Guest]);
        $member->save();

        $member->update(['level' => UserRole::User]);
        $member->update(['level' => UserRole::Guest]);
        $member->update(['level' => 'user']);

        $this->assertTrue($member->canTransitionTo(UserRole::Admin));
        $this->assertSame('user', $this->rejectedBy(fn () => $member->update(['level' => UserRole::SuperAdmin]))->from);
        $this->assertSame(UserRole::User, $member->fresh()->getAttribute('level'));
    }

    /**
     * Test that the transitions guard unlocking a locked record: a sent invoice can't go back to its draft, while
     * a ready one can, unfreezing what it froze.
     *
     * @return void
     */
    public function testGuardsUnlockingOfLockedRecord(): void
    {
        $sent = $this->createInvoice('sent');
        $ready = $this->createInvoice('ready');

        $this->rejectedBy(fn () => $sent->update(['status' => 'draft', 'total' => 500]));
        $ready->update(['status' => 'draft', 'total' => 500]);

        $this->assertSame(100, $sent->fresh()->getAttribute('total'));
        $this->assertSame(500, $ready->fresh()->getAttribute('total'));

        $this->expectException(RecordLockedException::class);

        $sent->fresh()->update(['total' => 500]);
    }
}
