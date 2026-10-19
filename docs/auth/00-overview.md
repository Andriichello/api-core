# Authentication

api-core signs users in with Laravel Sanctum: API clients (mobile apps, scripts, other servers) get an access token and
a refresh token, and a SPA on one of Sanctum's stateful domains signs in to its session cookie. Read this first to see
the routes `AuthController` gives an application and the guards and middleware behind them.

## Tokens or sessions

Both kinds of clients use the same routes. `login` looks at the request: when it has a session (Sanctum's stateful
middleware started one, because the request comes from a domain in `SANCTUM_STATEFUL_DOMAINS`), it signs in to that
session; any other request gets tokens. Later requests (`logout`, `switch`, `change-password`) act on whatever they were
authenticated with.

|                    | Tokens                                                          | Session (SPA)                                  |
|--------------------|-----------------------------------------------------------------|------------------------------------------------|
| Login response     | `{data: {token_type, access_token, expires_at, expires_in, refresh_token, refresh_expires_at, user}}` | `{data: {user}}` |
| Authenticates with | `Authorization: Bearer {access_token}`                          | the session cookie and `X-XSRF-TOKEN`          |
| Lifetime           | 8 hours; a refresh token, which works for 14 days, renews it    | the application's session lifetime            |
| Logout             | deletes the access token and its refresh token                  | invalidates the session                        |
| Switching accounts | issues a token for the other account, which can't switch again  | switches the session, which can switch on and back |

## Registering the routes

api-core registers no routes. The application registers the auth routes in `routes/api.php`, so they live under
`/api/auth/` and always answer JSON:

```php
use Core\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(fn () => AuthController::routes());
```

Don't put the `password.updated` middleware on these routes: a person whose password must be replaced still needs them
to sign in and set it. The `audit` middleware may wrap them like any other API route.

## Routes

| Method  | Path                                    | Name                           | Middleware                        | Description                                       |
|---------|-----------------------------------------|--------------------------------|-----------------------------------|---------------------------------------------------|
| `POST`  | `/api/auth/login`                       | `auth.login`                   | throttle                          | [Sign in](01-login.md) with email and password     |
| `POST`  | `/api/auth/refresh`                     | `auth.refresh`                 | throttle                          | [Exchange a refresh token](02-tokens.md)           |
| `POST`  | `/api/auth/forgot-password`             | `auth.forgot-password`         | throttle                          | [Email a password reset link](08-password-reset.md) |
| `POST`  | `/api/auth/reset-password`              | `auth.reset-password`          | throttle                          | [Reset the password](08-password-reset.md)         |
| `POST`  | `/api/auth/verify-email`                | `auth.verify-email`            | throttle                          | [Verify an email](09-email-verification.md)        |
| `POST`  | `/api/auth/accept-invitation`           | `auth.accept-invitation`       | throttle                          | [Accept an invitation](11-invitations.md)          |
| `GET`   | `/api/auth/me`                          | `auth.me`                      | `auth:sanctum`                    | [The signed-in user](04-me.md)                     |
| `PATCH` | `/api/auth/me`                          | `auth.me.update`               | `auth:sanctum`                    | [Update own name, timezone, locale](04-me.md)      |
| `POST`  | `/api/auth/logout`                      | `auth.logout`                  | `auth:sanctum`                    | Sign out ([tokens](02-tokens.md), [session](03-sessions.md)) |
| `POST`  | `/api/auth/switch`                      | `auth.switch`                  | `auth:sanctum`                    | [Switch to another account](05-switching-accounts.md) |
| `POST`  | `/api/auth/email/verification-notification` | `auth.verification-notification` | `auth:sanctum`, throttle | [Email a verification link](09-email-verification.md) |
| `POST`  | `/api/auth/change-password`             | `auth.change-password`         | `auth:sanctum`, throttle          | [Change the password](07-passwords.md)             |

A signed-in route answers `401` with `{"message": "Unauthenticated."}` without a valid token or session.

## Guards

- `auth:sanctum` is Sanctum's guard. It first asks the session guards listed in `sanctum.guard` (`web` by default) and
  then looks for a bearer token.
- Tokens are `Core\Models\AccessToken` records in `personal_access_tokens`; `AuthServiceProvider` makes it Sanctum's
  token model. A token is accepted only while it hasn't expired, its user can sign in (`User::canSignIn()`), and, for a
  token issued by [switching](05-switching-accounts.md), the user who switched can sign in too. Suspending a user or
  their organization therefore ends their tokens at once.
- The session guard and the password broker load users through the `users` provider of `config/auth.php`, so the
  application sets `AUTH_MODEL=Core\Models\User`.

## Middleware

| Middleware                              | Where it runs                                       | What it does                                        |
|-----------------------------------------|-----------------------------------------------------|-----------------------------------------------------|
| `throttle:api-core-auth`                | the routes marked "throttle" above                  | limits attempts (see below)                         |
| `auth:sanctum`                          | the signed-in auth routes, and the app's own routes | authenticates with a session or a token             |
| `Core\Http\Middleware\AuthenticateSession` | Sanctum's stateful middleware (bound in place of Sanctum's own) | [ends sessions](03-sessions.md) of users who can no longer sign in or whose password changed |
| `password.updated`                      | the application's own routes                        | [answers 403](10-required-password-update.md) until the person replaces a password someone else set |

## Throttling

The rate limiter `api-core-auth` (`AuthController::RATE_LIMITER`) allows `api-core.auth.rate_limit` attempts per minute
(10 by default) for each IP address and what the attempts are made on:

- the signed-in user (`email/verification-notification`, `change-password`), whatever the request gives, so sending
  other emails doesn't get a signed-in client more attempts at the current password;
- else the `email` field (`login`, `forgot-password`, `reset-password`);
- else the refresh token (`refresh`), by its id, so the refreshes of one client don't use up another's;
- else the account of the link (`verify-email`, `accept-invitation`), by its `id`.

The count is shared by all throttled routes, so a `login` and a `forgot-password` for the same email from the same
address draw from one budget. Over the limit, a route answers `429` with `{"message": "Too Many Attempts."}`.

## Configuration

| Key                                    | Env                                | Default                       | Description                          |
|----------------------------------------|------------------------------------|-------------------------------|--------------------------------------|
| `api-core.auth.access_token_lifetime`  | `API_CORE_ACCESS_TOKEN_LIFETIME`   | `480`                         | Minutes an access token works        |
| `api-core.auth.refresh_token_lifetime` | `API_CORE_REFRESH_TOKEN_LIFETIME`  | `20160`                       | Minutes a refresh token works        |
| `api-core.auth.rate_limit`             | `API_CORE_AUTH_RATE_LIMIT`         | `10`                          | Throttled attempts per minute        |
| `api-core.auth.password_reset_url`     | `API_CORE_PASSWORD_RESET_URL`      | `{APP_URL}/reset-password`    | SPA page of the password reset link  |
| `api-core.auth.email_verification_url` | `API_CORE_EMAIL_VERIFICATION_URL`  | `{APP_URL}/verify-email`      | SPA page of the verification link    |
| `api-core.auth.invitation_url`         | `API_CORE_INVITATION_URL`          | `{APP_URL}/accept-invitation` | SPA page of the invitation link      |
| `api-core.auth.invitation_lifetime`    | `API_CORE_INVITATION_LIFETIME`     | `10080`                       | Minutes an invitation link works     |
| `api-core.audit.auth_events`           | `API_CORE_AUDIT_AUTH_EVENTS`       | `false`                       | Record logins, failed logins, logouts and switches in the audit log |

Laravel's own `auth.verification.expire` (60 minutes) and `auth.passwords.users.expire` (60 minutes) set how long the
verification and password reset links work.

## Related

- [Login](01-login.md)
- [Access and refresh tokens](02-tokens.md)
- [Sessions](03-sessions.md)
- [Installation](../getting-started/02-installation.md)
- [Configuration](../getting-started/03-configuration.md)
- [Setting up a SPA](../getting-started/04-spa.md)
- [Middleware](../http/03-middleware.md)
- [Errors](../http/02-errors.md)
- [Recording events](../audit/03-recording-events.md)
