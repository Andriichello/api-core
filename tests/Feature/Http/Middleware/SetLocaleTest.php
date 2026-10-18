<?php

namespace Core\Tests\Feature\Http\Middleware;

use Core\Http\Middleware\SetLocale;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Class SetLocaleTest.
 *
 * Requests, which tell their locale, where English is the default, and Ukrainian is supported too.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Feature\Http\Middleware
 */
class SetLocaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register the routes, which tell the locale, for signed-in users, with the middleware listed before the
     * authentication, and for guests, and support English and Ukrainian.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.locale' => 'en', 'api-core.locales' => ['en', 'uk']]);

        $locale = fn () => ['locale' => app()->getLocale()];

        Route::middleware([SetLocale::ALIAS, 'auth:sanctum'])->get('locale', $locale);
        Route::middleware(SetLocale::ALIAS)->get('guest/locale', $locale);
    }

    /**
     * Ask the guest route for its locale with the given `Accept-Language`, or with an empty one, which is as good
     * as none (the test client sends one, unless it is replaced).
     *
     * @param string|null $acceptLanguage
     *
     * @return TestResponse
     */
    private function guestLocale(?string $acceptLanguage): TestResponse
    {
        return $this->getJson('guest/locale', ['Accept-Language' => $acceptLanguage ?? '']);
    }

    /**
     * Test that the signed-in user's locale wins over `Accept-Language`, though the route lists the middleware
     * before the authentication, and the response names it.
     *
     * @return void
     */
    public function testUsesTheUsersLocale(): void
    {
        // A real token, since the user is known only after the authentication with it
        $user = User::factory()->create(['locale' => 'uk']);
        $token = app(AccessTokenRepository::class)->create($user, 'test')->plainTextToken;

        $this->withToken($token)
            ->getJson('locale', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertJsonPath('locale', 'uk')
            ->assertHeader('Content-Language', 'uk');
    }

    /**
     * Test that a user's locale, which isn't supported, gives way to `Accept-Language`.
     *
     * @return void
     */
    public function testSkipsUnsupportedLocaleOfTheUser(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'fr']), 'sanctum');

        $this->getJson('locale', ['Accept-Language' => 'uk'])->assertJsonPath('locale', 'uk');
    }

    /**
     * Test that the best supported match of `Accept-Language` is used, regions included.
     *
     * @return void
     */
    public function testUsesTheBestMatchOfAcceptLanguage(): void
    {
        $this->guestLocale('uk-UA,uk;q=0.9,en;q=0.8')->assertJsonPath('locale', 'uk');
        $this->guestLocale('de-DE,de;q=0.9,uk;q=0.5')->assertJsonPath('locale', 'uk');
        $this->guestLocale('en-GB')->assertJsonPath('locale', 'en')->assertHeader('Content-Language', 'en');
    }

    /**
     * Test that the default is used without a header, or with one, which matches no supported locale.
     *
     * @return void
     */
    public function testFallsBackToTheDefault(): void
    {
        config(['app.locale' => 'uk', 'api-core.locales' => ['en']]);

        $this->guestLocale(null)->assertJsonPath('locale', 'uk');
        $this->guestLocale('de-DE,fr;q=0.5')->assertJsonPath('locale', 'uk');
        $this->guestLocale('en')->assertJsonPath('locale', 'en');
    }
}
