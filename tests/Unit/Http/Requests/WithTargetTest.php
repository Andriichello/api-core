<?php

namespace Core\Tests\Unit\Http\Requests;

use Core\Http\Requests\Crud\ShowRequest;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class WithTargetTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Http\Requests
 */
class WithTargetTest extends TestCase
{
    use RefreshDatabase;

    private function createOrganization(): Organization
    {
        return Organization::create([
            'slug' => fake()->unique()->slug(),
            'name' => fake()->company(),
        ]);
    }

    private function createUser(?Organization $organization = null): User
    {
        return User::query()->forceCreate([
            'organization_id' => $organization?->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password123',
        ]);
    }

    private function createRequest(?User $user, array $data = []): ShowRequest
    {
        $request = ShowRequest::create('/users', 'GET', $data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

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

    public function testTargetReadsIdFromInput(): void
    {
        $user = $this->createUser();

        $request = $this->createRequest($user, ['id' => $user->id]);

        $this->assertEquals($user->id, $request->getId());
        $this->assertTrue($request->target(User::class)?->is($user));
    }

    public function testTargetReturnsNullForModelOutsideOfUserScope(): void
    {
        $user = $this->createUser($this->createOrganization());
        $stranger = $this->createUser($this->createOrganization());

        $request = $this->createRequest($user)->setId($stranger->id);

        $this->assertNull($request->target(User::class));
    }

    public function testTargetReturnsNullWithoutUser(): void
    {
        $user = $this->createUser();

        $request = $this->createRequest(null)->setId($user->id);

        $this->assertNull($request->target(User::class));
    }

    public function testTargetOrFailThrowsForModelOutsideOfUserScope(): void
    {
        $user = $this->createUser($this->createOrganization());
        $stranger = $this->createUser($this->createOrganization());

        $request = $this->createRequest($user)->setId($stranger->id);

        $this->expectException(ModelNotFoundException::class);

        $request->targetOrFail(User::class);
    }

    public function testTargetOrFailReturnsModelVisibleToTheUser(): void
    {
        $organization = $this->createOrganization();

        $user = $this->createUser($organization);

        $request = $this->createRequest($user)->setId($organization->id);

        $target = $request->targetOrFail(Organization::class);

        $this->assertTrue($target->is($organization));
    }
}
