<?php

namespace Core\Repositories;

use Core\Models\AccessToken;
use Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
     * organization. A user, who switched to this one, is recorded on it,
     * along with the token, with which they switched, whose logout ends it.
     *
     * @param User $user
     * @param string $name
     * @param User|null $impersonator
     * @param AccessToken|null $issuer
     *
     * @return NewAccessToken
     */
    public function create(
        User $user,
        string $name,
        ?User $impersonator = null,
        ?AccessToken $issuer = null
    ): NewAccessToken {
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
            'issuer_token_id' => $issuer?->id,
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
     * ones stop working, so a refresh token can be used only once, and the new
     * access token takes over the tokens, which the old one switched to. It is
     * null when the refresh token isn't valid or its user can't sign in.
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

            AccessToken::query()
                ->where('issuer_token_id', $token->getKey())
                ->update(['issuer_token_id' => $newToken->accessToken->getKey()]);

            return [$newToken, $this->createRefreshToken($newToken->accessToken)];
        });
    }

    /**
     * Delete the token, along with its refresh token, and the tokens, which
     * were issued with it by switching to other accounts.
     *
     * @param AccessToken $token
     *
     * @return void
     */
    public function delete(AccessToken $token): void
    {
        AccessToken::query()->where('issuer_token_id', $token->getKey())->delete();

        $token->delete();
    }

    /**
     * Delete the tokens of every account of the people, who own the accounts,
     * deleted accounts included, along with the tokens, with which they switched
     * to other accounts, and the refresh tokens of both, but the given token.
     *
     * @param array<int, User> $users
     * @param AccessToken|null $except
     *
     * @return int The number of deleted tokens.
     */
    public function deleteOfPeople(array $users, ?AccessToken $except = null): int
    {
        if (empty($users)) {
            return 0;
        }

        return $this->deleteOfAccountsIn(
            User::query()
                ->withoutGlobalScope(SoftDeletingScope::class)
                ->ofPeople(...$users)
                ->select('users.id'),
            $except
        );
    }

    /**
     * Delete the tokens of the accounts, along with the tokens, with which they
     * switched to other accounts, and the refresh tokens of both.
     *
     * @param User ...$accounts
     *
     * @return int The number of deleted tokens.
     */
    public function deleteOfAccounts(User ...$accounts): int
    {
        if (empty($accounts)) {
            return 0;
        }

        return $this->deleteOfAccountsIn(array_map(fn (User $account) => $account->getKey(), $accounts));
    }

    /**
     * Delete the tokens of the accounts with the ids, along with the tokens,
     * with which they switched to other accounts, but the given token.
     *
     * @param Builder|array<int, int> $accountIds A query, which selects them, or the ids.
     * @param AccessToken|null $except
     *
     * @return int The number of deleted tokens.
     */
    protected function deleteOfAccountsIn(Builder|array $accountIds, ?AccessToken $except = null): int
    {
        return AccessToken::query()
            ->where(function (Builder $query) use ($accountIds) {
                $query
                    ->where(function (Builder $query) use ($accountIds) {
                        $query->where('tokenable_type', (new User)->getMorphClass())
                            ->whereIn('tokenable_id', $accountIds);
                    })
                    ->orWhereIn('impersonator_id', $accountIds);
            })
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->delete();
    }
}
