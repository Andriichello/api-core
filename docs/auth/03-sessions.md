# Sessions

A SPA served from one of Sanctum's stateful domains signs in to its session cookie instead of keeping tokens in the
browser. It uses the same auth routes as token clients; this document covers what differs for a session.

## Setup

The full setup is in [Setting up a SPA](../getting-started/04-spa.md). In short:

1. Call `$middleware->statefulApi()` in `bootstrap/app.php`, so API requests from the SPA run Sanctum's stateful
   middleware and get a session.
2. List the SPA's domain in `SANCTUM_STATEFUL_DOMAINS`, and set `SESSION_DOMAIN` when the SPA and the API are on
   different subdomains.
3. Set `supports_credentials` to `true` in `config/cors.php`.
4. Have the SPA send its cookies with every request, call `GET /sanctum/csrf-cookie` before the login, and send the
   `XSRF-TOKEN` cookie back in the `X-XSRF-TOKEN` header.

The decision is made per request: `login` signs in to a session when the request has one, which only requests from a
stateful domain do. Any other request gets [tokens](02-tokens.md), even from the same browser.

## Signing in

```http
GET /sanctum/csrf-cookie
Origin: https://app.example.com
```

```http
POST /api/auth/login
Origin: https://app.example.com
X-XSRF-TOKEN: {the value of the XSRF-TOKEN cookie}
Content-Type: application/json

{"email": "ann@example.com", "password": "secret-password"}
```

```json
{
    "data": {
        "user": {
            "id": 1,
            "organization_id": 1,
            "name": "Ann",
            "email": "ann@example.com",
            "impersonator": null
        }
    },
    "message": "OK"
}
```

The `user` is shortened here; it is the full [`MeResource`](04-me.md). The account is chosen as for any
[login](01-login.md). No token is issued. The session is signed in with the first guard of `sanctum.guard` (`web` by
default), which fires `Illuminate\Auth\Events\Login` with that guard's name, and gets a new id, so an id from before
the login can't be used to take it over.

## Signing out

`POST /api/auth/logout` from the SPA signs the session out, invalidates it and regenerates its CSRF token. It fires
`Illuminate\Auth\Events\Logout` with the session guard's name.

```json
{
    "message": "Logged out"
}
```

A session that was [switched](05-switching-accounts.md) to another account ends as a whole; signing in again starts
without the switch.

## What ends a session

`AuthServiceProvider` binds `Core\Http\Middleware\AuthenticateSession` in place of Sanctum's `AuthenticateSession`, so
Sanctum's stateful middleware runs it on every SPA request. It signs a session out, answering `401` with
`{"message": "Unauthenticated."}`, when:

- its user can no longer sign in (`User::canSignIn()`): the user or their organization is suspended, or the
  organization is deactivated. The session is flushed, so unsuspending the user doesn't bring it back;
- its user's password changed, by a [reset](08-password-reset.md) or a [change](07-passwords.md) made elsewhere.
  Sanctum keeps a hash of the password in the session and compares it on each request;
- it was switched to another account, and the user who switched it can't sign in, is deleted, or has another password
  than when they switched.

Sanctum stores the password hash only after a request that starts signed in. api-core's middleware stores it on the
login request too, so a session that hasn't made a request since signing in is signed out by a password change as
well.

The session that changes the password itself stays signed in, also when it is switched to a linked account: the new
hash is stored at the end of that request.

## No remember-me

Logging in never sets a "remember me" cookie, and the `users` table has no `remember_token` column. A session lasts as
long as the application's session lifetime (`config/session.php`), or until one of the cases above ends it.

## Related

- [Overview](00-overview.md)
- [Login](01-login.md)
- [The signed-in user](04-me.md)
- [Switching accounts](05-switching-accounts.md)
- [Setting up a SPA](../getting-started/04-spa.md)
- [Middleware](../http/03-middleware.md)
