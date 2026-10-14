<?php

namespace Core\Repositories;

use Core\Models\AccessToken;
use Core\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

/**
 * Class AccessTokenRepository.
 *
 * @author Andrii Prykhodko <andriichello@gmail.com>
 * @package Core\Repositories
 */
class AccessTokenRepository
{
    /**
     * Create an access token for the user, which acts in the user's
     * organization. A user, who switched to this one, is recorded on it.
     *
     * @param User $user
     * @param string $name
     * @param User|null $impersonator
     *
     * @return NewAccessToken
     */
    public function create(User $user, string $name, ?User $impersonator = null): NewAccessToken
    {
        $plainTextToken = $user->generateTokenString();

        /** @var AccessToken $token */
        $token = $user->tokens()->make([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'expires_at' => now()->addMinutes(config('api-core.auth.access_token_lifetime')),
        ]);

        $token->forceFill([
            'organization_id' => $user->organization_id,
            'impersonator_id' => $impersonator?->id,
        ])->save();

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    /**
     * Create a refresh token for the access token.
     *
     * @param PersonalAccessToken $token
     *
     * @return string
     * @throws LogicException
     */
    public function createRefreshToken(PersonalAccessToken $token): string
    {
        if (!$token instanceof AccessToken) {
            throw new LogicException('Refresh tokens need the ' . AccessToken::class . ' model.');
        }

        return $token->issueRefreshToken(
            now()->addMinutes(config('api-core.auth.refresh_token_lifetime'))
        );
    }

    /**
     * Exchange a refresh token for a new access token and refresh token. The old
     * ones stop working, so a refresh token can be used only once. It is null
     * when the refresh token isn't valid or its user can't sign in.
     *
     * @param string $refreshToken
     * @param string $name
     *
     * @return array{NewAccessToken, string}|null
     */
    public function refresh(string $refreshToken, string $name): ?array
    {
        $token = AccessToken::findByRefreshToken($refreshToken);
        $user = $token?->tokenable;

        if (!$user instanceof User || !$user->canSignIn()) {
            return null;
        }

        return DB::transaction(function () use ($token, $user, $name) {
            // Only one of the requests, which use the same refresh token
            // at the same time, deletes the old token and gets a new one
            if (AccessToken::query()->whereKey($token->getKey())->delete() === 0) {
                return null;
            }

            $newToken = $this->create($user, $name);

            return [$newToken, $this->createRefreshToken($newToken->accessToken)];
        });
    }

    /**
     * Delete the token, along with its refresh token.
     *
     * @param AccessToken $token
     *
     * @return void
     */
    public function delete(AccessToken $token): void
    {
        $token->delete();
    }
}
