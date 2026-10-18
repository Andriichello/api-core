<?php

namespace Core\Tests\Feature\Http\Controllers;

use Core\Http\Controllers\NotificationController;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Class NotificationControllerTest.
 *
 * Deadline reminders of a user and of another user, stored by the `database` channel.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Signed-in user.
     *
     * @var User
     */
    private User $user;

    /**
     * Another user, whose notifications the signed-in user can't reach.
     *
     * @var User
     */
    private User $other;

    /**
     * Register the routes of the notifications, and create the users.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        NotificationController::routes();

        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    /**
     * Send the user a deadline reminder of the given quarter, a minute after the previous one, so they are
     * ordered.
     *
     * @param User $user
     * @param int $quarter
     *
     * @return DatabaseNotification
     */
    private function remind(User $user, int $quarter): DatabaseNotification
    {
        $this->travel(1)->minutes();

        $user->notify(new class($quarter) extends Notification
        {
            public function __construct(
                private readonly int $quarter
            ) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function databaseType(object $notifiable): string
            {
                return 'deadline-reminder';
            }

            public function toArray(object $notifiable): array
            {
                return ['quarter' => $this->quarter];
            }
        });

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->firstOrFail();

        return $notification;
    }

    /**
     * Test that the user's notifications are listed newest first, without the other user's ones.
     *
     * @return void
     */
    public function testListsOwnNotificationsNewestFirst(): void
    {
        $first = $this->remind($this->user, 1);
        $second = $this->remind($this->user, 2);
        $this->remind($this->other, 3);

        $this->actingAs($this->user)
            ->getJson('notifications')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->getKey(), $first->getKey()])
            ->assertJsonPath('data.0.type', 'deadline-reminder')
            ->assertJsonPath('data.0.data', ['quarter' => 2])
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonStructure(['data' => [['id', 'type', 'data', 'read_at', 'created_at']], 'meta']);
    }

    /**
     * Test that notifications of the same second are listed by their ids, so the pages don't repeat or skip any.
     *
     * @return void
     */
    public function testListsNotificationsOfTheSameSecondInStableOrder(): void
    {
        $this->freezeSecond();

        $ids = collect(range(1, 3))
            ->map(fn () => $this->user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'deadline-reminder',
                'data' => [],
            ])->getKey())
            ->sortDesc()
            ->values()
            ->all();

        $this->actingAs($this->user)
            ->getJson('notifications')
            ->assertOk()
            ->assertJsonPath('data.*.id', $ids);

        foreach ($ids as $index => $id) {
            $this->getJson('notifications?page[size]=1&page[number]=' . ($index + 1))
                ->assertOk()
                ->assertJsonPath('data.*.id', [$id]);
        }
    }

    /**
     * Test that only the unread notifications are listed and counted, when asked.
     *
     * @return void
     */
    public function testListsAndCountsUnreadNotifications(): void
    {
        $read = $this->remind($this->user, 1);
        $unread = $this->remind($this->user, 2);
        $this->remind($this->other, 3);

        $read->markAsRead();

        $this->actingAs($this->user)
            ->getJson('notifications?unread=1')
            ->assertJsonPath('data.*.id', [$unread->getKey()]);

        $this->getJson('notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 1);
    }

    /**
     * Test that a notification is marked as read, but another user's one isn't found.
     *
     * @return void
     */
    public function testMarksOwnNotificationAsRead(): void
    {
        $notification = $this->remind($this->user, 1);
        $others = $this->remind($this->other, 2);

        $this->actingAs($this->user)
            ->postJson("notifications/{$notification->getKey()}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->getKey());

        $this->postJson("notifications/{$others->getKey()}/read")->assertNotFound();

        $this->assertNotNull($notification->fresh()->getAttribute('read_at'));
        $this->assertNull($others->fresh()->getAttribute('read_at'));
    }

    /**
     * Test that all the user's unread notifications are marked as read, but not the other user's ones.
     *
     * @return void
     */
    public function testMarksAllOwnNotificationsAsRead(): void
    {
        $this->remind($this->user, 1);
        $this->remind($this->user, 2);
        $others = $this->remind($this->other, 3);

        $this->actingAs($this->user)
            ->postJson('notifications/read')
            ->assertOk()
            ->assertJsonPath('data.read', 2);

        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertNull($others->fresh()->getAttribute('read_at'));
    }

    /**
     * Test that a notification is deleted, but another user's one isn't found.
     *
     * @return void
     */
    public function testDeletesOwnNotification(): void
    {
        $notification = $this->remind($this->user, 1);
        $others = $this->remind($this->other, 2);

        $this->actingAs($this->user)
            ->deleteJson("notifications/{$notification->getKey()}")
            ->assertOk()
            ->assertJsonPath('message', 'Deleted');

        $this->deleteJson("notifications/{$others->getKey()}")->assertNotFound();

        $this->assertModelMissing($notification);
        $this->assertModelExists($others);
    }

    /**
     * Test that guests can't reach notifications.
     *
     * @return void
     */
    public function testGuestsCannotReachNotifications(): void
    {
        $this->getJson('notifications')->assertUnauthorized();
        $this->postJson('notifications/read')->assertUnauthorized();
    }
}
