<?php

namespace Core\Tests\Feature\Http\Middleware;

use Core\Http\Controllers\Auth\AuthController;
use Core\Http\Middleware\ForceJsonOnApi;
use Core\Tests\TestCase;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;

/**
 * Class ForceJsonOnApiTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Middleware
 */
class ForceJsonOnApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register the authentication routes under the API path, and a route,
     * which fails, under the API path and outside of it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/auth')->group(fn () => AuthController::routes());

        Route::get('api/broken', fn () => throw new RuntimeException('Broken'));
        Route::get('broken', fn () => throw new RuntimeException('Broken'));
    }

    /**
     * Assert that the response is JSON.
     *
     * @param TestResponse $response
     *
     * @return TestResponse
     */
    private function assertIsJson(TestResponse $response): TestResponse
    {
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        return $response;
    }

    /**
     * Assert that the response is HTML.
     *
     * @param TestResponse $response
     *
     * @return TestResponse
     */
    private function assertIsHtml(TestResponse $response): TestResponse
    {
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));

        return $response;
    }

    /**
     * Test that an unauthenticated API request gets a JSON error instead of a redirect to a login page.
     *
     * @return void
     */
    public function testRendersUnauthenticatedApiRequestsAsJson(): void
    {
        $this->assertIsJson($this->get('/api/auth/me'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Test that an API request with invalid data gets JSON validation errors instead of a redirect back.
     *
     * @return void
     */
    public function testRendersValidationErrorsOfApiRequestsAsJson(): void
    {
        $this->assertIsJson($this->post('/api/auth/login', ['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test that a request to a missing API route gets a JSON error.
     *
     * @return void
     */
    public function testRendersMissingApiRoutesAsJson(): void
    {
        $this->assertIsJson($this->get('/api/missing'))->assertNotFound();
        $this->assertIsJson($this->get('/api'))->assertNotFound();
    }

    /**
     * Test that a server error of an API request is JSON, which hides the error outside of debug mode.
     *
     * @return void
     */
    public function testRendersServerErrorsOfApiRequestsAsJson(): void
    {
        Exceptions::fake();
        config(['app.debug' => false]);

        $this->assertIsJson($this->get('/api/broken'))
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'Server Error']);
    }

    /**
     * Test that an API request gets JSON, even when it asks for HTML.
     *
     * @return void
     */
    public function testOverridesAcceptHeaderOfApiRequests(): void
    {
        $this->assertIsJson($this->get('/api/missing', ['Accept' => 'text/html']))->assertNotFound();
    }

    /**
     * Test that requests to other paths, including ones, which only resemble the API path, are left as they are.
     *
     * @return void
     */
    public function testLeavesOtherPathsAsTheyAre(): void
    {
        Exceptions::fake();

        $this->assertIsHtml($this->get('/missing'))->assertNotFound();
        $this->assertIsHtml($this->get('/apix'))->assertNotFound();
        $this->assertIsHtml($this->get('/docs/api/missing'))->assertNotFound();
        $this->assertIsHtml($this->get('/broken'))->assertInternalServerError();
    }

    /**
     * Test that the API paths are taken from the config.
     *
     * @return void
     */
    public function testUsesConfiguredPaths(): void
    {
        config(['api-core.http.json_paths' => ['v1/*']]);

        $this->assertIsJson($this->get('/v1/missing'))->assertNotFound();
        $this->assertIsHtml($this->get('/api/missing'))->assertNotFound();
    }

    /**
     * Test that an empty list of API paths turns it off.
     *
     * @return void
     */
    public function testCanBeTurnedOff(): void
    {
        config(['api-core.http.json_paths' => []]);

        $this->assertIsHtml($this->get('/api/missing'))->assertNotFound();
    }

    /**
     * Test that it runs before the other global middleware, so their errors are JSON too.
     *
     * @return void
     */
    public function testRunsFirstAmongGlobalMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        if (!$kernel instanceof Kernel) {
            $this->fail('The HTTP kernel is not an instance of ' . Kernel::class);
        }

        $this->assertSame(ForceJsonOnApi::class, $kernel->getGlobalMiddleware()[0]);
    }
}
