# Access and refresh tokens

API clients that aren't a SPA on a stateful domain sign in with tokens: a short-lived access token authenticates
requests, and a refresh token renews it. This document covers the token model, refreshing, logging out, and when tokens
stop working or are deleted.

## The token model

`Core\Models\AccessToken` extends Sanctum's `PersonalAccessToken` and is Sanctum's token model in an app with api-core.
Its table, `personal_access_tokens`, has these columns on top of Sanctum's:

| Column               | Description                                                                     |
|----------------------|---------------------------------------------------------------------------------|
| `organization_id`    | The organization of the token's user, when it was issued                         |
| `impersonator_id`    | The user who [switched](05-switching-accounts.md) to the token's user, if any   |
| `refresh_token`      | The SHA-256 hash of the refresh token's secret; hidden from serialization        |
| `refresh_expires_at` | When the refresh token stops working                                             |
| `metadata`           | A JSON column, which api-core doesn't fill (cast to an array)                    |

A token is named after the `User-Agent` of the request that got it, and has the abilities `['*']`. Its methods are
`isExpired()`, `isImpersonated()`, `impersonator()`, `organization()`, `issueRefreshToken()`, `findByRefreshToken()` and
`prunable()`.

## Lifetimes

The access token's `expires_at` is `api-core.auth.access_token_lifetime` minutes (480, 8 hours) after it is issued, and
the refresh token's `refresh_expires_at` is `api-core.auth.refresh_token_lifetime` minutes (20160, 14 days) after it.

On every request, a token is accepted only while it hasn't expired and its user can sign in (`User::canSignIn()`). A
token issued by switching also needs the user who switched to be able to sign in. So a token stops working at once when
its user, or that user's organization, is suspended, and no token has to be deleted for it.

## Refreshing

`POST /api/auth/refresh` exchanges a refresh token for a new access token and a new refresh token. It needs no
`Authorization` header, so it works after the access token has expired.

| Field           | Rules                    |
|-----------------|--------------------------|
| `refresh_token` | required, string, max 255 |

```http
POST /api/auth/refresh
Content-Type: application/json

{"refresh_token": "1|wJoSHp4XSGRu7vlIS1u7U2GcuIyn7JWyw6OaTkMl8doDyg0osSHahxsmqMujU78n"}
```

```json
{
    "data": {
        "token_type": "Bearer",
        "access_token": "3|8Uj41zJv14gHCbAdyEec899enlcGggleh8Pd3vlz630d02c2",
        "expires_at": "2026-10-20T05:01:18+00:00",
        "expires_in": 28799,
        "refresh_token": "3|zhX5704G3vEBMCR6xezxUDigNT0wCHBJYEe9F3avM3GiR8bK0FYSHztDEPxWP5iV",
        "refresh_expires_at": "2026-11-02T21:01:18+00:00"
    },
    "message": "OK"
}
```

The response has no `user`. The old access token and its refresh token are deleted, so each refresh token works once.
A refresh token that is malformed, wrong, expired or used already, or whose user can't sign in, gets `401` with
`{"message": "The refresh token is invalid or has expired."}`, and the client signs in again.

Why it is built so:

- **Hashed.** A refresh token is `{token id}|{64 random characters}`, and only the SHA-256 hash of the secret is stored,
  as Sanctum does with access tokens. Reading the table gives no working tokens.
- **Single use.** Every refresh replaces the pair, so a refresh token copied from a client can't be replayed once the
  client has used it. Of several requests that use the same refresh token at the same time, only one deletes the old
  token and gets a new pair; the others get `401`.

## Logging out

`POST /api/auth/logout` with a bearer token deletes that access token along with its refresh token. The user's other
tokens, on other devices, keep working. It fires `Illuminate\Auth\Events\Logout` with the guard `sanctum`.

```json
{
    "message": "Logged out"
}
```

## When tokens are deleted

| Action                                                                  | Deleted tokens                                                                                     |
|-------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|
| [`logout`](#logging-out)                                                | the current token                                                                                  |
| [`change-password`](07-passwords.md)                                    | every token of the person's accounts and every token they switched with, except the current one    |
| [`reset-password`](08-password-reset.md)                                | every token of the accounts of each person with the email, and the tokens they switched with       |
| [`accept-invitation`](11-invitations.md) with a password                | every token of the person's accounts and the tokens they switched with                             |
| [`verify-email`](09-email-verification.md), when it links the account   | the account's tokens and the tokens it switched with, as it takes another password                 |
| [Re-inviting a deleted user](../users/02-re-inviting-deleted-users.md) | the account's tokens and the tokens it switched with                                               |

## Pruning

Expired tokens stay in the table until they are pruned. Schedule Laravel's `model:prune` for `AccessToken`, which
deletes the tokens whose access token and refresh token have both expired:

```php
// routes/console.php
use Core\Models\AccessToken;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [AccessToken::class]])->daily();
```

Don't use `sanctum:prune-expired`: it looks at the access token only, so it would delete tokens whose refresh token
still works and sign their clients out.

## Issuing tokens in code

`Core\Repositories\AccessTokenRepository` issues and deletes tokens:

| Method                                        | Description                                                              |
|-----------------------------------------------|--------------------------------------------------------------------------|
| `create($user, $name, $impersonator = null)`  | Issue an access token (a Sanctum `NewAccessToken`) with the configured lifetime |
| `createRefreshToken($token)`                  | Issue a refresh token for an access token, replacing the one it had       |
| `refresh($refreshToken, $name)`               | Exchange a refresh token; `null` when it isn't valid                      |
| `delete($token)`                              | Delete a token with its refresh token                                     |
| `deleteOfPeople($users, $except = null)`      | Delete the tokens of every account of the people, and their switched tokens |
| `deleteOfAccounts(...$accounts)`              | Delete the tokens of the accounts, and their switched tokens              |

In tests, issue a token through the repository rather than Sanctum's `createToken()`, which sets no expiry unless it
is given one and records no organization:

```php
use Core\Repositories\AccessTokenRepository;

$token = app(AccessTokenRepository::class)->create($user, 'Test');

$this->withToken($token->plainTextToken)->getJson('/api/projects')->assertOk();
```

## Related

- [Overview](00-overview.md)
- [Login](01-login.md)
- [Switching accounts](05-switching-accounts.md)
- [Passwords](07-passwords.md)
- [Scheduled tasks](../getting-started/05-scheduled-tasks.md)
- [Factories and seeders](../database/01-factories-and-seeders.md)
