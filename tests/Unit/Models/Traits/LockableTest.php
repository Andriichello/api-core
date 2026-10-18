<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Exceptions\RecordLockedException;
use Core\Models\BaseModel;
use Core\Models\Interfaces\LockableInterface;
use Core\Models\Traits\Lockable;
use Core\Models\Traits\LockedByParent;
use Core\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Class LockableTest.
 *
 * Invoices, which are locked past their draft, but whose status, notes and sending date may still change, and their
 * items, which are locked with them.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class LockableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the invoices, whose table is created for the tests.
     *
     * @var class-string<BaseModel&LockableInterface>
     */
    private string $invoices;

    /**
     * Class of the invoices' items, whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $items;

    /**
     * Create the tables of invoices and their items, and their models.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft');
            $table->string('number')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->json('snapshot')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('description');
            $table->timestamps();
        });

        $this->invoices = get_class(new class extends BaseModel implements LockableInterface
        {
            use Lockable;
            use SoftDeletes;

            protected $table = 'invoices';

            protected $fillable = ['status', 'number', 'total', 'snapshot', 'notes', 'sent_at'];

            protected $attributes = ['status' => 'draft', 'total' => 0];

            protected $casts = ['snapshot' => 'array'];

            public function isLocked(): bool
            {
                return $this->getAttribute('status') !== 'draft';
            }

            public function unlockedAttributes(): array
            {
                return ['status', 'notes', 'sent_at'];
            }
        });

        $items = new class extends BaseModel
        {
            use LockedByParent;

            public static string $invoices;

            protected $table = 'invoice_items';

            protected $fillable = ['invoice_id', 'description'];

            public function invoice(): BelongsTo
            {
                return $this->belongsTo(self::$invoices, 'invoice_id');
            }

            public function lockingParent(): (Model&LockableInterface)|null
            {
                return $this->getRelationValue('invoice');
            }
        };

        $items::$invoices = $this->invoices;
        $this->items = $items::class;
    }

    /**
     * Create an invoice with the given attributes.
     *
     * @param array<string, mixed> $attributes
     *
     * @return BaseModel&LockableInterface
     */
    private function createInvoice(array $attributes = []): BaseModel&LockableInterface
    {
        $invoice = new $this->invoices(['number' => 'INV-1', 'total' => 100, ...$attributes]);
        $invoice->save();

        return $invoice;
    }

    /**
     * Get the exception, which the given callback throws, as the record is locked.
     *
     * @param callable $callback
     *
     * @return RecordLockedException
     */
    private function lockedBy(callable $callback): RecordLockedException
    {
        try {
            $callback();
        } catch (RecordLockedException $exception) {
            return $exception;
        }

        $this->fail('The locked record was changed.');
    }

    /**
     * Test that an unlocked record changes freely, and that a change, which locks it, is allowed as a whole.
     *
     * @return void
     */
    public function testUnlockedRecordChangesFreely(): void
    {
        $invoice = $this->createInvoice();

        $invoice->update(['total' => 150, 'number' => 'INV-2']);
        $invoice->update(['status' => 'ready', 'total' => 200, 'snapshot' => ['total' => 200]]);

        $this->assertTrue($invoice->fresh()->isLocked());
        $this->assertSame(200, $invoice->fresh()->getAttribute('total'));
    }

    /**
     * Test that a locked record's attributes don't change, and the exception names them.
     *
     * @return void
     */
    public function testLockedRecordKeepsItsAttributes(): void
    {
        $invoice = $this->createInvoice(['status' => 'ready']);

        $exception = $this->lockedBy(fn () => $invoice->update(['total' => 999, 'number' => 'X', 'notes' => 'Ok']));

        $this->assertSame(['total', 'number'], $exception->attributes);
        $this->assertSame("The record is locked, so its total, number can't be changed.", $exception->getMessage());
        $this->assertTrue($invoice->is($exception->record));
        $this->assertSame(100, $invoice->fresh()->getAttribute('total'));
        $this->assertNull($invoice->fresh()->getAttribute('notes'));
    }

    /**
     * Test that a locked record's unlocked attributes and timestamps change.
     *
     * @return void
     */
    public function testLockedRecordChangesUnlockedAttributes(): void
    {
        $invoice = $this->createInvoice(['status' => 'ready']);

        $this->travel(1)->minutes();
        $invoice->update(['status' => 'sent', 'sent_at' => now(), 'notes' => 'Sent by mail']);

        $this->travel(1)->minutes();
        $invoice->touch();

        $this->assertSame('sent', $invoice->fresh()->getAttribute('status'));
        $this->assertSame('Sent by mail', $invoice->fresh()->getAttribute('notes'));
    }

    /**
     * Test that a change, which unlocks a record, is allowed as a whole.
     *
     * @return void
     */
    public function testChangeThatUnlocksIsAllowed(): void
    {
        $invoice = $this->createInvoice(['status' => 'ready', 'snapshot' => ['total' => 100]]);

        $invoice->update(['status' => 'draft', 'snapshot' => null, 'total' => 120]);

        $fresh = $invoice->fresh();

        $this->assertFalse($fresh->isLocked());
        $this->assertNull($fresh->getAttribute('snapshot'));
        $this->assertSame(120, $fresh->getAttribute('total'));
    }

    /**
     * Test that a locked record can't be deleted, softly or for good, though an unlocked one can.
     *
     * @return void
     */
    public function testLockedRecordCannotBeDeleted(): void
    {
        $locked = $this->createInvoice(['status' => 'ready']);
        $draft = $this->createInvoice();

        $exception = $this->lockedBy(fn () => $locked->delete());

        $this->assertSame("The record is locked, so it can't be deleted.", $exception->getMessage());
        $this->lockedBy(fn () => $locked->forceDelete());

        $draft->delete();

        $this->assertNotSoftDeleted($locked);
        $this->assertSoftDeleted($draft);
    }

    /**
     * Test that a record's unsaved changes don't decide, whether it was locked.
     *
     * @return void
     */
    public function testWasLockedJudgesStoredState(): void
    {
        $invoice = $this->createInvoice(['status' => 'ready']);
        $invoice->setAttribute('status', 'draft');

        $this->assertTrue($invoice->wasLocked());
        $this->assertFalse($invoice->isLocked());
    }

    /**
     * Test that the parts of a locked record can't be created, changed or deleted, while those of an unlocked one
     * can.
     *
     * @return void
     */
    public function testPartsOfLockedRecordCannotChange(): void
    {
        $invoice = $this->createInvoice();

        $item = new $this->items(['invoice_id' => $invoice->getKey(), 'description' => 'Hours']);
        $item->save();
        $item->update(['description' => 'Development']);

        $invoice->update(['status' => 'ready']);
        $item = $this->items::query()->findOrFail($item->getKey());

        $exception = $this->lockedBy(fn () => $item->update(['description' => 'Changed']));
        $this->assertSame(
            "The record, to which it belongs, is locked, so it can't be changed.",
            $exception->getMessage()
        );
        $this->assertTrue($invoice->is($exception->record));

        $this->lockedBy(fn () => $item->delete());
        $new = new $this->items(['invoice_id' => $invoice->getKey(), 'description' => 'New']);
        $this->lockedBy(fn () => $new->save());

        $this->assertSame(['Development'], $this->items::query()->pluck('description')->all());
    }

    /**
     * Test that the exception answers with 409 and its message.
     *
     * @return void
     */
    public function testExceptionAnswersWithConflict(): void
    {
        $invoice = $this->createInvoice(['status' => 'ready']);
        $exception = $this->lockedBy(fn () => $invoice->delete());

        $request = Request::create('api/invoices/1', 'DELETE', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->app->make(ExceptionHandler::class)->render($request, $exception);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(
            "The record is locked, so it can't be deleted.",
            json_decode($response->getContent(), true)['message']
        );
    }
}
