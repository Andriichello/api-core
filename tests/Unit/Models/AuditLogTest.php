<?php

namespace Core\Tests\Unit\Models;

use Core\Enums\UserRole;
use Core\Helpers\CurrentOrganization;
use Core\Models\AuditLog;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Role;

/**
 * Class AuditLogTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a recorded entry is caused by the signed-in user on the subject, in the subject's organization,
     * and named after the subject's morph and the event.
     *
     * @return void
     */
    public function testRecordsEntryOfSignedInUserOnSubject(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $acme->id]);

        $this->actingAs($user);

        // The subject's organization comes before the current one
        $entry = CurrentOrganization::run(
            $acme,
            fn () => AuditLog::record('exported', $globex, ['metadata' => ['format' => 'csv']])
        );

        $this->assertSame($globex->id, $entry->organization_id);
        $this->assertTrue($user->is($entry->causer));
        $this->assertTrue($globex->is($entry->subject));
        $this->assertSame(AuditLog::SOURCE_MANUAL, $entry->source);
        $this->assertSame('exported', $entry->event);
        $this->assertSame('models.organizations.exported', $entry->name);
        $this->assertSame(['format' => 'csv'], $entry->fresh()->metadata);
        $this->assertNotNull($entry->created_at);
    }

    /**
     * Test that the given causer replaces the signed-in user, and that null means none.
     *
     * @return void
     */
    public function testRecordsGivenCauser(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user);

        $this->assertTrue($other->is(AuditLog::record('imported', null, ['causer' => $other])->causer));

        $entry = AuditLog::record('imported', null, ['causer' => null]);

        $this->assertNull($entry->causer_type);
        $this->assertNull($entry->causer_id);
        $this->assertSame('imported', $entry->name);
    }

    /**
     * Test that an entry without a subject of an organization is in the current organization, or else in the
     * causer's one.
     *
     * @return void
     */
    public function testFallsBackToCurrentOrCausersOrganization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $acme->id]);

        $this->actingAs($user);

        $this->assertSame($acme->id, AuditLog::record('imported')->organization_id);
        $this->assertSame(
            $globex->id,
            CurrentOrganization::run($globex, fn () => AuditLog::record('imported'))->organization_id
        );
        $this->assertNull(AuditLog::record('imported', null, ['causer' => null])->organization_id);
    }

    /**
     * Test that the name and the description are cut to the lengths of their columns.
     *
     * @return void
     */
    public function testCutsNameAndDescription(): void
    {
        $entry = AuditLog::record(str_repeat('n', 300), null, ['description' => str_repeat('d', 300)]);

        $this->assertSame(191, mb_strlen($entry->name));
        $this->assertSame(255, mb_strlen((string) $entry->description));
    }

    /**
     * Test that entries can't be changed or deleted one by one.
     *
     * @return void
     */
    public function testEntriesCannotBeChangedOrDeleted(): void
    {
        $entry = AuditLog::factory()->create(['event' => 'sent']);

        try {
            $entry->update(['event' => 'forged']);

            $this->fail('An entry was changed.');
        } catch (LogicException) {
            $this->assertSame('sent', AuditLog::query()->findOrFail($entry->id)->event);
        }

        $this->expectException(LogicException::class);

        $entry->delete();
    }

    /**
     * Test that pruning deletes the entries older than the retention, and none without one.
     *
     * @return void
     */
    public function testPrunesEntriesOlderThanRetention(): void
    {
        $old = AuditLog::factory()->create(['created_at' => now()->subDays(40)]);
        $recent = AuditLog::factory()->create(['created_at' => now()->subDays(20)]);

        $this->artisan('model:prune', ['--model' => AuditLog::class])->assertSuccessful();
        $this->assertSame(2, AuditLog::query()->count());

        config(['api-core.audit.retention_days' => 30]);

        $this->artisan('model:prune', ['--model' => AuditLog::class])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    /**
     * Test that users see the entries of the organization, in which they act, a super admin of no organization
     * sees all of them, and guests and other users of no organization see none.
     *
     * @return void
     */
    public function testIndexShowsEntriesOfTheActingOrganization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();

        $acmeEntry = AuditLog::factory()->onSubject($acme)->create();
        $globexEntry = AuditLog::factory()->onSubject($globex)->create();
        $internalEntry = AuditLog::factory()->create();

        $user = User::factory()->create(['organization_id' => $acme->id]);
        $internal = User::factory()->create(['organization_id' => null]);

        Role::findOrCreate(UserRole::SuperAdmin->value);
        $superAdmin = User::factory()->create(['organization_id' => null]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $idsFor = fn (?User $viewer) => AuditLog::query()->index($viewer)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$acmeEntry->id], $idsFor($user));
        $this->assertSame([$acmeEntry->id, $globexEntry->id, $internalEntry->id], $idsFor($superAdmin));
        $this->assertSame([$globexEntry->id], CurrentOrganization::run($globex, fn () => $idsFor($superAdmin)));
        $this->assertSame([], $idsFor($internal));
        $this->assertSame([], $idsFor(null));
    }
}
