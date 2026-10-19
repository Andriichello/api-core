<?php

namespace Core\Tests\Unit\Models\Traits;

use Core\Models\AuditLog;
use Core\Models\BaseModel;
use Core\Models\Organization;
use Core\Models\Traits\Audited;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Class AuditedTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models\Traits
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class AuditedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Class of the soft-deletable invoices, whose changes are audited, and whose table is created for the tests.
     *
     * @var class-string<BaseModel>
     */
    private string $invoices;

    /**
     * Class of the invoices, which are deleted for good, kept in the same table.
     *
     * @var class-string<BaseModel>
     */
    private string $drafts;

    /**
     * The morph map before the test, which is restored after it.
     *
     * @var array<string, class-string>
     */
    private array $morphMap;

    /**
     * Organization of the invoices.
     *
     * @var Organization
     */
    private Organization $organization;

    /**
     * Create the table of invoices and their models, which the morph map names, and which hide `secret` and
     * leave `notes` out of the audit log. The table is temporary, so that MySQL doesn't commit the test's
     * transaction, as it does on creating a table.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('invoices', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('number');
            $table->unsignedInteger('total');
            $table->string('secret')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->invoices = get_class(new class extends BaseModel
        {
            use Audited;
            use SoftDeletes;

            protected $table = 'invoices';

            protected $fillable = ['organization_id', 'number', 'total', 'secret', 'notes'];

            protected $hidden = ['secret'];

            public function auditExcluded(): array
            {
                return ['notes'];
            }
        });

        $this->drafts = get_class(new class extends BaseModel
        {
            use Audited;

            protected $table = 'invoices';

            protected $fillable = ['organization_id', 'number', 'total'];
        });

        $this->morphMap = Relation::morphMap();

        Relation::morphMap([
            'tests.invoices' => $this->invoices,
            'tests.drafts' => $this->drafts,
        ]);

        $this->organization = Organization::factory()->create();
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
     * Create an invoice of the given class.
     *
     * @param class-string<BaseModel>|null $class
     *
     * @return BaseModel
     */
    private function createInvoice(?string $class = null): BaseModel
    {
        $invoice = new ($class ?? $this->invoices)([
            'organization_id' => $this->organization->id,
            'number' => 'INV-1',
            'total' => 100,
            'secret' => 'token',
            'notes' => 'Draft',
        ]);

        $invoice->save();

        return $invoice;
    }

    /**
     * Get the events of the entries of the given model's audit log, oldest first.
     *
     * @param BaseModel $model
     *
     * @return array<int, string>
     */
    private function eventsOf(BaseModel $model): array
    {
        return AuditLog::query()->ofSubject($model)->orderBy('id')->pluck('event')->all();
    }

    /**
     * Get the latest entry of the given model's audit log.
     *
     * @param BaseModel $model
     *
     * @return AuditLog
     */
    private function latestOf(BaseModel $model): AuditLog
    {
        return AuditLog::query()->ofSubject($model)->orderByDesc('id')->firstOrFail();
    }

    /**
     * Test that a created model is recorded with its new values, by the signed-in user, in its organization,
     * without the key, timestamps and excluded attributes, and with the hidden ones masked.
     *
     * @return void
     */
    public function testRecordsCreatedModel(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $invoice = $this->createInvoice();
        $entry = $this->latestOf($invoice);

        $this->assertSame('created', $entry->event);
        $this->assertSame(AuditLog::SOURCE_MODEL, $entry->source);
        $this->assertSame('tests.invoices.created', $entry->name);
        $this->assertSame($this->organization->id, $entry->organization_id);
        $this->assertTrue($user->is($entry->causer));
        $this->assertTrue($invoice->is($entry->subject));
        $this->assertSameIgnoringKeyOrder(
            ['new' => [
                'organization_id' => $this->organization->id,
                'number' => 'INV-1',
                'total' => 100,
                'secret' => '[hidden]',
            ]],
            $entry->changes
        );
    }

    /**
     * Test that an update is recorded with the old and new values of the changed attributes only.
     *
     * @return void
     */
    public function testRecordsChangedAttributesOfUpdate(): void
    {
        $invoice = $this->createInvoice();

        $invoice->update(['total' => 150, 'secret' => 'other', 'notes' => 'Final']);

        $this->assertSameIgnoringKeyOrder(
            ['old' => ['total' => 100, 'secret' => '[hidden]'], 'new' => ['total' => 150, 'secret' => '[hidden]']],
            $this->latestOf($invoice)->changes
        );
    }

    /**
     * Test that an update, which changes only excluded attributes or timestamps, isn't recorded.
     *
     * @return void
     */
    public function testSkipsUpdateWithoutAuditedChanges(): void
    {
        $invoice = $this->createInvoice();

        $this->travel(1)->minutes();

        $invoice->update(['notes' => 'Final']);
        $invoice->touch();

        $this->assertSame(['created'], $this->eventsOf($invoice));
    }

    /**
     * Test that a soft delete, a restore and a permanent delete are recorded as such, the deletes with the old
     * values, and the restore without an update besides it.
     *
     * @return void
     */
    public function testRecordsDeletesAndRestores(): void
    {
        $invoice = $this->createInvoice();

        if (!method_exists($invoice, 'restore')) {
            $this->fail('The invoices must be soft-deletable.');
        }

        $invoice->delete();
        $deleted = $this->latestOf($invoice);

        $invoice->restore();
        $restored = $this->latestOf($invoice);

        $invoice->forceDelete();

        $this->assertSame(['created', 'deleted', 'restored', 'force_deleted'], $this->eventsOf($invoice));
        $this->assertSameIgnoringKeyOrder(['number' => 'INV-1', 'total' => 100], array_intersect_key(
            $deleted->changes['old'] ?? [],
            ['number' => true, 'total' => true]
        ));
        $this->assertNull($restored->changes);
        $this->assertArrayHasKey('old', $this->latestOf($invoice)->changes ?? []);
    }

    /**
     * Test that deleting a model, which can't be soft-deleted, is recorded as a delete.
     *
     * @return void
     */
    public function testRecordsDeleteOfModelWithoutSoftDeletes(): void
    {
        $draft = $this->createInvoice($this->drafts);

        $draft->delete();

        $this->assertSame(['created', 'deleted'], $this->eventsOf($draft));
    }

    /**
     * Test that a rolled back change leaves no entry, and a change without a signed-in user has no causer.
     *
     * @return void
     */
    public function testRolledBackChangeLeavesNoEntry(): void
    {
        $invoice = $this->createInvoice();

        try {
            DB::transaction(function () use ($invoice) {
                $invoice->update(['total' => 999]);

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
            $this->assertSame(['created'], $this->eventsOf($invoice));
        }

        $this->assertNull($this->latestOf($invoice)->causer_id);
    }

    /**
     * Test that the model's entries are its audit log.
     *
     * @return void
     */
    public function testAuditLogsAreTheModelsEntries(): void
    {
        $invoice = $this->createInvoice();
        $this->createInvoice();

        $invoice->update(['total' => 200]);

        if (!method_exists($invoice, 'auditLogs')) {
            $this->fail('The invoices must be audited.');
        }

        $this->assertSame(['created', 'updated'], $invoice->auditLogs()->orderBy('id')->pluck('event')->all());
    }
}
