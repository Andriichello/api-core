# Login

`POST /api/auth/login` signs a user in with an email and a password. An API client gets an access token and a refresh
token; a SPA, whose request has a session, is signed in to that session.

## Request

| Field          | Rules                                       | Notes                                                    |
|----------------|---------------------------------------------|----------------------------------------------------------|
| `email`        | required, string, email, max 255            | Matched regardless of case (emails are stored lowercase) |
| `password`     | required, string                            |                                                          |
| `organization` | nullable, string, max 255                   | The slug of an organization; send it only when asked     |
| `remember`     | sometimes, boolean                          | A SPA's login outlives its session (see [Sessions](03-sessions.md#staying-signed-in)) |

```http
POST /api/auth/login
Content-Type: application/json

{"email": "ann@example.com", "password": "secret-password"}
```

## Choosing the account

An email may have an account in several organizations, so `LoginRequest::authenticate()` first finds every account
with the email whose password matches (`UserRepository::findByCredentials()`). The password is checked against the
person's main account, as all accounts of a person share one (see [Passwords](07-passwords.md)). With `organization`,
only the account in the organization with that slug is considered. Then:

- No account matches: `422` on `email`, "These credentials do not match our records."
- One account matches: it signs in.
- Several accounts match, all of one person, and their main account is among them: the main account signs in. The
  person [switches](05-switching-accounts.md) to the others from there.
- Otherwise, as when the matches belong to different people: `422` on `organization`, "The email is used in several
  organizations, so choose one of them." The client asks the user for the organization and sends the request again
  with its slug.

Accounts are known to belong to one person only once they are [linked](06-linked-accounts.md) by a verified email.
Until then, accounts with the same email in different organizations count as different people with their own
passwords. Asking for the organization only when the email and password match accounts of different people keeps the
field out of most logins: linked accounts of one person sign in to their main account, and a password, which matches a
single account, picks that one. Accounts without an organization can't be chosen by `organization`.

## Suspended accounts

The chosen account must be able to sign in (`User::canSignIn()`): neither the user nor their organization is suspended,
and the organization isn't deactivated. Otherwise the login answers `422` on `email`, "This account is suspended." A
deleted organization deletes its users along with it, so their login fails as unknown credentials instead.

## Response with tokens

```json
{
    "data": {
        "token_type": "Bearer",
        "access_token": "1|psg1ogx0XOjm3lwj62rDnyNDuZHdaW4Ha4RhmRT5c503a0e5",
        "expires_at": "2026-10-20T05:01:12+00:00",
        "expires_in": 28799,
        "refresh_token": "1|wJoSHp4XSGRu7vlIS1u7U2GcuIyn7JWyw6OaTkMl8doDyg0osSHahxsmqMujU78n",
        "refresh_expires_at": "2026-11-02T21:01:12+00:00",
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

`expires_in` is in whole seconds. `user` is shortened here; it is the full [`MeResource`](04-me.md). The client sends
`Authorization: Bearer {access_token}` and renews the pair with the [refresh token](02-tokens.md).

## Response with a session

A request from the SPA gets the user only, and its session cookie authenticates the following requests:

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

Signing in gives the session a new id, so an id from before the login can't be used to take it over, and clears any
[switch](05-switching-accounts.md) the session made before. See [Sessions](03-sessions.md).

## What a login records

- `users.last_login_at` is set to the time of the login.
- Laravel's `Illuminate\Auth\Events\Login` fires: with the guard `sanctum` for tokens, and with the session guard
  (`web`) for a session.
- A failed login fires `Illuminate\Auth\Events\Failed` with the guard `sanctum` and credentials holding the email only,
  never the password. Its user is the account, which matched but can't sign in, or `null` when nothing matched.
- With `api-core.audit.auth_events` on, the audit log records `login` and `login_failed` (see
  [Recording events](../audit/03-recording-events.md)).

## Errors

| Status | Field          | Message                                                               |
|--------|----------------|-----------------------------------------------------------------------|
| `422`  | `email`        | These credentials do not match our records.                           |
| `422`  | `email`        | This account is suspended.                                            |
| `422`  | `organization` | The email is used in several organizations, so choose one of them.    |
| `422`  | any field      | The usual validation messages, e.g. "The email field is required."    |
| `429`  |                | Too Many Attempts.                                                    |

A validation error has Laravel's shape:

```json
{
    "message": "The email is used in several organizations, so choose one of them.",
    "errors": {
        "organization": ["The email is used in several organizations, so choose one of them."]
    }
}
```

Login attempts count towards the `api-core-auth` limit of the email and IP address (see [Overview](00-overview.md)).

## Related

- [Overview](00-overview.md)
- [Access and refresh tokens](02-tokens.md)
- [Sessions](03-sessions.md)
- [Linked accounts](06-linked-accounts.md)
- [Passwords](07-passwords.md)
- [The User model](../users/00-user-model.md)
- [Errors](../http/02-errors.md)
