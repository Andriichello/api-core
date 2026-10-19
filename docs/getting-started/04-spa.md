# SPA with sessions

A browser SPA, which runs on the same site as the API, can sign in with a session cookie instead of keeping tokens in
JavaScript. The same auth routes serve both: a request from the SPA signs in to its session, and any other request,
such as one of a mobile app, gets tokens.

## How a request gets a session

`$middleware->statefulApi()` adds Sanctum's `EnsureFrontendRequestsAreStateful` to the `api` middleware group. When the
host of a request's `Referer` or `Origin` header is one of `sanctum.stateful`, the request gets encrypted cookies, a
session, CSRF protection and api-core's `AuthenticateSession`. `AuthController` sees the session and signs the user in
to it, instead of issuing tokens. Other requests get no session, so `POST /api/auth/login` answers them with tokens.

## Setup

1. Make the API stateful for the SPA in `bootstrap/app.php`:

   ```php
   ->withMiddleware(function (Middleware $middleware): void {
       $middleware->statefulApi();
   })
   ```

2. Name the SPA's domain and share the session cookie in `.env`:

   ```env
   SANCTUM_STATEFUL_DOMAINS=app.example.com
   SESSION_DOMAIN=.example.com
   SESSION_DRIVER=database
   ```

   - `SANCTUM_STATEFUL_DOMAINS` is a comma-separated list of hosts, with the port, when it isn't the default one
     (`localhost:5173`).
   - `SESSION_DOMAIN` is needed, when the SPA and the API are on different subdomains (`app.example.com` and
     `api.example.com`), so both see the cookie. They must share the parent domain, since cookies don't cross sites.
   - Any session driver works; api-core's migrations create the `sessions` table of the `database` driver.

3. Allow the SPA's origin with credentials. Publish the CORS config with `php artisan config:publish cors` and set:

   ```php
   'paths' => ['api/*', 'sanctum/csrf-cookie'],
   'allowed_origins' => ['https://app.example.com'],
   'supports_credentials' => true,
   ```

4. Send cookies and the CSRF token from the SPA. With axios:

   ```js
   axios.defaults.withCredentials = true;
   axios.defaults.withXSRFToken = true;
   ```

   axios then copies the `XSRF-TOKEN` cookie into the `X-XSRF-TOKEN` header. Other clients do the same by hand.

## Signing in

Get the CSRF cookie first, then post the credentials:

```http
GET /sanctum/csrf-cookie
```

It answers `204 No Content` with the `XSRF-TOKEN` cookie.

```http
POST /api/auth/login
Content-Type: application/json
X-XSRF-TOKEN: eyJpdiI6…

{"email": "ann@acme.test", "password": "secret-password"}
```

The response sets the session cookie and returns the user, without tokens:

```json
{
    "data": {
        "user": {
            "id": 1,
            "organization_id": 1,
            "name": "Ann Lee",
            "email": "ann@acme.test",
            "timezone": "UTC",
            "locale": "en",
            "metadata": null,
            "email_verified_at": "2026-10-19T21:06:35.000000Z",
            "suspended_at": null,
            "last_login_at": "2026-10-19T21:06:35.000000Z",
            "created_at": "2026-10-19T21:06:35.000000Z",
            "updated_at": "2026-10-19T21:06:35.000000Z",
            "deleted_at": null,
            "password_updated_at": "2026-10-19T21:06:35.000000Z",
            "main_account_id": null,
            "is_password_update_required": false,
            "organization": {"id": 1, "slug": "acme", "name": "Acme"},
            "roles": ["user"],
            "permissions": [
                "view models.users",
                "viewAny models.users",
                "viewAny models.organizations",
                "view models.organizations"
            ],
            "impersonator": null
        }
    },
    "message": "OK"
}
```

From then on, the SPA's requests are authenticated by the cookie on every route under `auth:sanctum`.
`POST /api/auth/logout` signs the session out, invalidates it and regenerates the CSRF token, answering
`{"message": "Logged out"}`.

## Behavior

- There is no remember-me: a session ends after `SESSION_LIFETIME` minutes without requests, or on logout.
- `AuthenticateSession` signs a session out, with a 401, when its user can no longer sign in (the user or the
  organization is suspended, or the organization is deactivated) or the person's password changes. A session, which
  was switched to another account, also ends, when the user, who switched it, can't sign in or changes the password.
- Unlike a token, a switched session can switch back to the account, which signed in (see
  [Switching accounts](../auth/05-switching-accounts.md)).
- A missing or stale CSRF token is answered with `419` and `{"message": "CSRF token mismatch."}`; fetch
  `/sanctum/csrf-cookie` again.
- The login rules are the same as with tokens: choosing the organization when needed, and refusing suspended accounts
  (see [Login](../auth/01-login.md)).

## Related

- [Sessions](../auth/03-sessions.md)
- [Authentication](../auth/00-overview.md)
- [Login](../auth/01-login.md)
- [Switching accounts](../auth/05-switching-accounts.md)
- [Middleware](../http/03-middleware.md)
- [Installation](02-installation.md)
