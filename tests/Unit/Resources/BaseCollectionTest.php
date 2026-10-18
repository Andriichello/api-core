<?php

namespace Core\Tests\Unit\Resources;

use Core\Http\Resources\BaseCollection;
use Core\Http\Resources\BaseResource;
use Core\Http\Resources\UserCollection;
use Core\Http\Resources\UserResource;
use Core\Models\Organization;
use Core\Models\User;
use Core\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Class BaseCollectionTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Resources
 */
class BaseCollectionTest extends TestCase
{
    /**
     * Make a request that counts how many times each parameter is read.
     *
     * @param array $parameters
     *
     * @return Request&object{reads: array<string, int>}
     */
    private function countingRequest(array $parameters): Request
    {
        $class = get_class(new class extends Request
        {
            /** @var array<string, int> */
            public array $reads = [];

            public function input($key = null, $default = null): mixed
            {
                if (is_string($key)) {
                    $this->reads[$key] = ($this->reads[$key] ?? 0) + 1;
                }

                return parent::input($key, $default);
            }
        });

        return $class::create('/users', 'GET', $parameters);
    }

    /**
     * Make a collection of users, each with its organization loaded,
     * whose items allow the `organization` include and `email` append.
     *
     * @param int $count
     *
     * @return BaseCollection
     */
    private function collectionOfUsers(int $count): BaseCollection
    {
        $resourceClass = get_class(new class(null) extends BaseResource
        {
            protected array $allowedIncludes = ['organization'];

            protected array $allowedAppends = ['email'];
        });

        $users = new Collection;

        for ($i = 1; $i <= $count; $i++) {
            $user = new User(['name' => "User $i", 'email' => "user$i@example.com"]);
            $user->setRelation('organization', new Organization(['name' => "Org $i"]));

            $users->push($user);
        }

        return new class($users, $resourceClass) extends BaseCollection
        {
            public function __construct(mixed $resource, string $collects)
            {
                $this->collects = $collects;

                parent::__construct($resource);
            }
        };
    }

    public function testRequestIsReadOnceForAllItems(): void
    {
        $request = $this->countingRequest(['include' => 'organization', 'append' => 'email']);

        $this->collectionOfUsers(3)->toArray($request);

        $this->assertSame(1, $request->reads['include']);
        $this->assertSame(1, $request->reads['append']);
    }

    public function testEachItemGetsWhatTheRequestAsksFor(): void
    {
        $request = Request::create('/users', 'GET', ['include' => 'organization', 'append' => 'email']);

        $items = $this->collectionOfUsers(2)->toArray($request);

        $this->assertCount(2, $items);
        $this->assertSame('Org 1', data_get($items[0], 'organization.name'));
        $this->assertSame('user1@example.com', $items[0]['email']);
        $this->assertSame('Org 2', data_get($items[1], 'organization.name'));
        $this->assertSame('user2@example.com', $items[1]['email']);
    }

    public function testPassedAppendsAndIncludesAreUsedInsteadOfTheRequest(): void
    {
        $request = $this->countingRequest(['include' => 'organization', 'append' => 'email']);

        $this->collectionOfUsers(3)
            ->setPassedAppends([])
            ->setPassedIncludes([])
            ->toArray($request);

        $this->assertArrayNotHasKey('include', $request->reads);
        $this->assertArrayNotHasKey('append', $request->reads);
    }

    /**
     * Test that the base collection collects base resources, while its subclasses collect their own.
     *
     * @return void
     */
    public function testBaseCollectionCollectsBaseResources(): void
    {
        $this->assertSame(BaseResource::class, new BaseCollection([])->resourceOfItems());
        $this->assertSame(UserResource::class, new UserCollection([])->resourceOfItems());
    }

    /**
     * Test that the items of a base collection give their attributes, without the relations they loaded.
     *
     * @return void
     */
    public function testItemsOfBaseCollectionLeaveOutLoadedRelations(): void
    {
        $user = new User(['name' => 'Test']);
        $user->setRelation('organization', new Organization(['name' => 'Test Org']));

        $items = new BaseCollection(new Collection([$user]))
            ->setPassedIncludes([])
            ->setPassedAppends([])
            ->toArray(Request::create('/users'));

        $this->assertSame('Test', $items[0]['name']);
        $this->assertArrayNotHasKey('organization', $items[0]);
    }
}
