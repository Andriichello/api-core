<?php

namespace Core\Tests\Unit\Http\Requests;

use Core\Http\Requests\Crud\ShowRequest;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Class WithTargetTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Requests
 */
class WithTargetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an organization with a unique slug.
     *
     * @return Organization
     */
    private function createOrganization(): Organization
    {
        return Organization::create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);
    }

    /**
     * Create a user in the organization, if it is given.
     *
     * @param Organization|null $organization
     *
     * @return User
     */
    private function createUser(?Organization $organization = null): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
    }

    /**
     * Make a show request of the user, if it is given, with the given data.
     *
     * @param User|null $user
     * @param array<string, mixed> $data
     *
     * @return ShowRequest
     */
    private function createRequest(?User $user, array $data = []): ShowRequest
    {
        $request = ShowRequest::create('/users', 'GET', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Test that the target is the model with the id of the request, when it is in the scope of the user.
     *
     * @return void
     */
    public function testTargetReturnsModelVisibleToTheUser(): void
    {
        $organization = $this->createOrganization();

        $user = $this->createUser($organization);
        $colleague = $this->createUser($organization);

        $request = $this->createRequest($user)->setId($colleague->id);

        $target = $request->target(User::class);

        $this->assertInstanceOf(User::class, $target);
        $this->assertTrue($target->is($colleague));
    }

    /**
     * Test that the id of the target is read from the input of the request, when none is set.
     *
     * @return void
     */
    public function testTargetReadsIdFromInput(): void
    {
        $user = $this->createUser();

        $request = $this->createRequest($user, ['id' => $user->id]);

        $this->assertEquals($user->id, $request->getId());
        $this->assertTrue($request->target(User::class)?->is($user));
    }

    /**
     * Test that the target is null, when its model is out of the scope of the user.
     *
     * @return void
     */
    public function testTargetReturnsNullForModelOutsideOfUserScope(): void
    {
        $user = $this->createUser($this->createOrganization());
        $stranger = $this->createUser($this->createOrganization());

        $request = $this->createRequest($user)->setId($stranger->id);

        $this->assertNull($request->target(User::class));
    }

    /**
     * Test that the target is null, when the request has no user.
     *
     * @return void
     */
    public function testTargetReturnsNullWithoutUser(): void
    {
        $user = $this->createUser();

        $request = $this->createRequest(null)->setId($user->id);

        $this->assertNull($request->target(User::class));
    }

    /**
     * Test that getting the target or failing throws a model not found exception, when the model is out of the scope of
     * the user.
     *
     * @return void
     */
    public function testTargetOrFailThrowsForModelOutsideOfUserScope(): void
    {
        $user = $this->createUser($this->createOrganization());
        $stranger = $this->createUser($this->createOrganization());

        $request = $this->createRequest($user)->setId($stranger->id);

        $this->expectException(ModelNotFoundException::class);

        $request->targetOrFail(User::class);
    }

    /**
     * Test that getting the target or failing gives the model, when it is in the scope of the user, here the
     * organization of the user.
     *
     * @return void
     */
    public function testTargetOrFailReturnsModelVisibleToTheUser(): void
    {
        $organization = $this->createOrganization();

        $user = $this->createUser($organization);

        $request = $this->createRequest($user)->setId($organization->id);

        $target = $request->targetOrFail(Organization::class);

        $this->assertTrue($target->is($organization));
    }

    /**
     * Test that the loaded target is reused, whether the model is given by its class or as an instance, and is
     * loaded again for another id.
     *
     * @return void
     */
    public function testTargetIsReusedUntilTheIdChanges(): void
    {
        $organization = $this->createOrganization();

        $user = $this->createUser($organization);
        $colleague = $this->createUser($organization);

        $request = $this->createRequest($user)->setId($colleague->id);
        $target = $request->targetOrFail(User::class);

        DB::enableQueryLog();

        $this->assertSame($target, $request->target(User::class));
        $this->assertSame($target, $request->target(new User));
        $this->assertSame($target, $request->targetOrFail(new User));
        $this->assertSame([], DB::getQueryLog());

        $request->setId($user->id);

        $this->assertTrue($user->is($request->target(new User)));
        $this->assertNotInstanceOf(User::class, $request->target(Organization::class));
    }
}
