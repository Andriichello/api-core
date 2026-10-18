<?php

namespace Core\Tests\Feature\Http\Controllers;

use Core\Enums\UserRole;
use Core\Http\Controllers\UserController;
use Core\Models\Organization;
use Core\Models\User;
use Core\Notifications\InvitationNotification;
use Core\Repositories\RoleRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Class UserControllerTest.
 *
 * Users of Acme, managed by its admin, with the default roles of `api-core.roles` (admin, user and guest), and
 * a manager, whose role may change users, but not their roles.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Controllers
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Organization, whose users are managed.
     *
     * @var Organization
     */
    private Organization $acme;

    /**
     * Admin of the organization.
     *
     * @var User
     */
    private User $admin;

    /**
     * User of the organization.
     *
     * @var User
     */
    private User $member;

    /**
     * Register the routes of the users, sync the roles of the config, with a manager between the admin and the
     * user, and create the users of the organization.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        UserController::routes();

        $roles = config('api-core.roles');
        array_splice($roles, 1, 0, [[
            'name' => 'manager',
            'permissions' => [User::class => ['all']],
        ]]);
        config(['api-core.roles' => $roles]);

        app(RoleRepository::class)->sync();

        $this->acme = Organization::factory()->create(['name' => 'Acme']);
        $this->admin = $this->createUser(UserRole::Admin->value);
        $this->member = $this->createUser(UserRole::User->value);

        Notification::fake();
    }

    /**
     * Create a user of the organization with the given roles.
     *
     * @param string ...$roles
     *
     * @return User
     */
    private function createUser(string ...$roles): User
    {
        $user = User::factory()->create(['organization_id' => $this->acme->id]);
        $user->assignRole($roles);

        return $user;
    }

    /**
     * Test that the users of the organization are listed with the names of their roles, without the users of
     * another organization.
     *
     * @return void
     */
    public function testListsUsersOfTheOrganizationWithRoles(): void
    {
        User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $response = $this->actingAs($this->admin)->getJson('users')->assertOk();

        $this->assertEqualsCanonicalizing([$this->admin->id, $this->member->id], $response->json('data.*.id'));
        $this->assertContains([UserRole::User->value], $response->json('data.*.roles'));
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
    }

    /**
     * Test that a person is invited to an account in the organization with the roles, and emailed, while
     * a given password is ignored.
     *
     * @return void
     */
    public function testInvitesPersonWithRoles(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('users', [
                'name' => 'Ann',
                'email' => 'Ann@Example.com',
                'roles' => [UserRole::User->value],
                'password' => 'known-password',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Invited')
            ->assertJsonPath('data.email', 'ann@example.com')
            ->assertJsonPath('data.roles', [UserRole::User->value]);

        $user = User::query()->findOrFail($response->json('data.id'));

        $this->assertSame($this->acme->id, $user->organization_id);
        $this->assertFalse(Hash::check('known-password', $user->password));

        Notification::assertSentTo(
            $user,
            InvitationNotification::class,
            fn (InvitationNotification $notification) => $this->admin->is($notification->inviter)
        );
    }

    /**
     * Test that an invitation needs an email, which is new to the organization, and roles, which the admin may
     * assign, though an email of another organization is fine.
     *
     * @return void
     */
    public function testInvitationIsValidated(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('users', ['name' => 'Twin', 'email' => strtoupper($this->member->email)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('users', [
            'name' => 'Boss',
            'email' => 'boss@example.com',
            'roles' => [UserRole::SuperAdmin->value, 'unknown'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles.0', 'roles.1']);

        $other = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->postJson('users', ['name' => 'Other', 'email' => $other->email])->assertCreated();
    }

    /**
     * Test that a user, whose role may only view users, can't invite anyone.
     *
     * @return void
     */
    public function testInvitingNeedsPermission(): void
    {
        $this->actingAs($this->member)
            ->postJson('users', ['name' => 'Ann', 'email' => 'ann@example.com'])
            ->assertForbidden();

        $this->assertSame(0, User::query()->where('email', 'ann@example.com')->count());
    }

    /**
     * Test that a user's name and roles are updated, while roles, which the admin may not assign, stay.
     *
     * @return void
     */
    public function testUpdatesNameAndAssignableRoles(): void
    {
        Role::findOrCreate('billing');
        $this->member->assignRole('billing');

        $this->actingAs($this->admin)
            ->patchJson("users/{$this->member->id}", ['name' => 'Renamed', 'roles' => [UserRole::Guest->value]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertEqualsCanonicalizing(
            [UserRole::Guest->value, 'billing'],
            $this->member->fresh()->getRoleNames()->all()
        );
    }

    /**
     * Test that a user's email and password can't be changed by another user.
     *
     * @return void
     */
    public function testEmailAndPasswordCannotBeChanged(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("users/{$this->member->id}", ['email' => 'new@example.com', 'password' => 'set-by-admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fields');

        $member = $this->member->fresh();

        $this->assertNotSame('new@example.com', $member->email);
        $this->assertFalse(Hash::check('set-by-admin', $member->password));
    }

    /**
     * Test that a manager, whose role may change users, but not their roles, can rename a user, but not change
     * their roles.
     *
     * @return void
     */
    public function testChangingRolesNeedsPermissions(): void
    {
        $manager = $this->createUser('manager');

        $this->actingAs($manager)
            ->patchJson("users/{$this->member->id}", ['name' => 'Renamed'])
            ->assertOk();

        $this->patchJson("users/{$this->member->id}", ['roles' => [UserRole::Guest->value]])->assertForbidden();
        $this->postJson('users', ['name' => 'Ann', 'email' => 'ann@example.com', 'roles' => ['user']])
            ->assertForbidden();

        $this->assertSame([UserRole::User->value], $this->member->fresh()->getRoleNames()->all());
    }

    /**
     * Test that a user, who ranks above the manager, can't be changed or deleted by them.
     *
     * @return void
     */
    public function testUserRankingAboveCannotBeManaged(): void
    {
        $manager = $this->createUser('manager');

        $this->actingAs($manager)
            ->patchJson("users/{$this->admin->id}", ['name' => 'Renamed'])
            ->assertForbidden();

        $this->deleteJson("users/{$this->admin->id}")->assertForbidden();

        $this->assertNotSame('Renamed', $this->admin->fresh()->name);
        $this->assertNotSoftDeleted($this->admin);
    }

    /**
     * Test that a user is deleted, but the admin can't delete themselves.
     *
     * @return void
     */
    public function testDeletesUserButNotOneself(): void
    {
        $this->actingAs($this->admin)
            ->deleteJson("users/{$this->member->id}")
            ->assertOk();

        $this->deleteJson("users/{$this->admin->id}")->assertForbidden();

        $this->assertSoftDeleted($this->member);
        $this->assertNotSoftDeleted($this->admin);
    }

    /**
     * Test that an invitation is emailed again, unless it has been accepted.
     *
     * @return void
     */
    public function testResendsPendingInvitation(): void
    {
        $this->actingAs($this->admin)
            ->postJson('users', ['name' => 'Ann', 'email' => 'ann@example.com'])
            ->assertCreated();

        $invited = User::query()->where('email', 'ann@example.com')->firstOrFail();

        $this->postJson("users/$invited->id/invitation")
            ->assertOk()
            ->assertJsonPath('message', 'Invitation sent');

        Notification::assertSentToTimes($invited, InvitationNotification::class, 2);

        $this->postJson("users/{$this->member->id}/invitation")
            ->assertConflict()
            ->assertJsonPath('message', 'The invitation has been accepted already.');
    }
}
