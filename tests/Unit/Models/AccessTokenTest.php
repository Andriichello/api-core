<?php

namespace Core\Tests\Unit\Models;

use Core\Models\AccessToken;
use Core\Models\User;
use Core\Repositories\AccessTokenRepository;
use Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class AccessTokenTest.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Tests\Unit\Models
 */
class AccessTokenTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an access token for a new user, which is issued to the impersonator, if it is given.
     *
     * @param User|null $impersonator
     *
     * @return AccessToken
     */
    private function createToken(?User $impersonator = null): AccessToken
    {
        $user = User::factory()->create();

        $token = app(AccessTokenRepository::class)
            ->create($user, 'Test', $impersonator)
            ->accessToken;

        if (!$token instanceof AccessToken) {
            $this->fail('The token is not an instance of ' . AccessToken::class);
        }

        return $token;
    }

    /**
     * Test that only the hash of a refresh token is stored, and it is hidden from serialization.
     *
     * @return void
     */
    public function testStoresOnlyHashOfRefreshToken(): void
    {
        $token = $this->createToken();

        $refreshToken = $token->issueRefreshToken(now()->addDay());
        [$id, $secret] = explode('|', $refreshToken);

        $this->assertSame((string) $token->id, $id);
        $this->assertSame(hash('sha256', $secret), $token->fresh()->refresh_token);
        $this->assertArrayNotHasKey('refresh_token', $token->toArray());
    }

    /**
     * Test that a token is found by its refresh token, but not by a wrong or malformed one.
     *
     * @return void
     */
    public function testFindsTokenByRefreshToken(): void
    {
        $token = $this->createToken();
        $refreshToken = $token->issueRefreshToken(now()->addDay());

        $this->assertTrue(AccessToken::findByRefreshToken($refreshToken)?->is($token));
        $this->assertNull(AccessToken::findByRefreshToken($token->id . '|wrong'));
        $this->assertNull(AccessToken::findByRefreshToken('wrong'));
        $this->assertNull(AccessToken::findByRefreshToken('x|' . explode('|', $refreshToken)[1]));
    }

    /**
     * Test that a token isn't found by an expired refresh token.
     *
     * @return void
     */
    public function testDoesNotFindTokenByExpiredRefreshToken(): void
    {
        $token = $this->createToken();
        $refreshToken = $token->issueRefreshToken(now()->addMinute());

        $this->travel(2)->minutes();

        $this->assertNull(AccessToken::findByRefreshToken($refreshToken));
    }

    /**
     * Test that issuing a new refresh token invalidates the old one.
     *
     * @return void
     */
    public function testNewRefreshTokenReplacesOldOne(): void
    {
        $token = $this->createToken();
        $old = $token->issueRefreshToken(now()->addDay());
        $new = $token->issueRefreshToken(now()->addDay());

        $this->assertNull(AccessToken::findByRefreshToken($old));
        $this->assertTrue(AccessToken::findByRefreshToken($new)?->is($token));
    }

    /**
     * Test that a token is impersonated only when it has an impersonator.
     *
     * @return void
     */
    public function testDeterminesImpersonation(): void
    {
        $this->assertFalse($this->createToken()->isImpersonated());
        $this->assertTrue($this->createToken(User::factory()->create())->isImpersonated());
    }

    /**
     * Test that pruning deletes the tokens, whose access and refresh tokens have both expired.
     *
     * @return void
     */
    public function testPrunesTokensWhoseAccessAndRefreshTokensHaveExpired(): void
    {
        $expired = $this->createToken();
        $expired->issueRefreshToken(now()->addHour());

        $refreshable = $this->createToken();
        $refreshable->issueRefreshToken(now()->addDays(2));

        $withoutRefresh = $this->createToken();
        $valid = $this->createToken();

        $this->travel(1)->day();
        $valid->forceFill(['expires_at' => now()->addHour()])->save();

        $this->artisan('model:prune', ['--model' => [AccessToken::class]])->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelMissing($withoutRefresh);
        $this->assertModelExists($refreshable);
        $this->assertModelExists($valid);
    }
}
