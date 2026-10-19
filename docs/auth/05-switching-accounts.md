# Switching accounts

`POST /api/auth/switch` lets a person with accounts in several organizations, or a super admin, act as another account
without signing in to it. A token client gets a token for that account; a SPA's session is switched in place.

## Who may switch where

- A main account may switch to the accounts [linked](06-linked-accounts.md) to it.
- A super admin may switch to any account.
- A linked account may switch to neither its main account nor the other accounts of its person, and only a super
  admin may switch to an account of another person.
- The account switched to must be able to sign in (`User::canSignIn()`).
- A token issued by switching may not switch at all (`403`), whatever its account may do.

`User::canImpersonateUser()` holds the rule. It is checked for the user on whose behalf the switch is made
(`SwitchAccountRequest::originalUser()`): with a token, the token's own user; with a session, the user who signed in to
it, even after the session was switched.

## Request

| Field     | Rules                                                                                     |
|-----------|-------------------------------------------------------------------------------------------|
| `user_id` | required, integer, an account that exists and isn't deleted, not the current account     |

## With tokens

```http
POST /api/auth/switch
Authorization: Bearer 1|psg1ogx0XOjm3lwj62rDnyNDuZHdaW4Ha4RhmRT5c503a0e5
Content-Type: application/json

{"user_id": 2}
```

```json
{
    "data": {
        "token_type": "Bearer",
        "access_token": "2|GIFU1iIwRsK9JPcosy0W6KXCGlfKBTeVtQQJrFz923251310",
        "expires_at": "2026-10-20T05:01:12+00:00",
        "expires_in": 28799,
        "user": {
            "id": 2,
            "organization_id": 2,
            "main_account_id": 1,
            "impersonator": {"id": 1, "organization_id": 1, "name": "Ann", "email": "ann@example.com"}
        }
    },
    "message": "OK"
}
```

(`user` is shortened; it is the full [`MeResource`](04-me.md).) The new token belongs to the account switched to, with
the user who switched recorded as its `impersonator_id`. It lives `api-core.auth.access_token_lifetime` minutes and has
no refresh token. The client keeps its own token and refresh token, sends the switched token while it acts as the
other account, and goes back to its own token when it is done or the switched token expires. `logout` with the switched
token deletes it; logging out of the client's own token leaves the switched token working until it expires.

## With sessions

A request from the SPA switches the session itself: the session now belongs to the account, keeps the id of the user
who signed in and a hash of their password, and gets a new id. The response is `{data: {user}}`, with `impersonator`
set. The session may switch on to any account the user who signed in may switch to, and back: switching to that user
clears the impersonator.

## Why a switched token can't switch again or back

A token issued by switching (one with an `impersonator_id`) is refused by `switch` with `403`
(`A switched account may not switch again.`): it can't go back to its impersonator, nor on to other accounts, even when
its own account may switch, as a main account with linked accounts or another super admin may.

Switching on would also let access outlive its source: the next token would name the account switched to, not the
user who switched first, as its impersonator, so suspending that user wouldn't end it.

This limits what a leaked token can do. A switched token opens the account it was issued for, for at most the access
token's lifetime; it can't be refreshed, and the impersonator's rights don't come with it, so it can't reach the
impersonator's own account or the accounts they may switch to. The client loses nothing: it still holds the
impersonator's own token to switch elsewhere.

A session is different. It isn't a credential a client passes around, but the browser's cookie, which works only for
requests from the SPA's stateful domains, and it stands for the user who signed in as a whole; a switch only changes
which account it acts as. So a switched session can switch on and back, and the rules are still checked for the user
who signed in.

## When switched access ends

| Event                                                              | Switched token | Switched session |
|--------------------------------------------------------------------|----------------|------------------|
| `logout` with it                                                   | deleted        | signed out       |
| The access token's lifetime passes                                 | expires        | —                |
| The user who switched is suspended or deleted, or their organization is suspended or deactivated | refused (`401`) | signed out (`401`) |
| The user who switched changes or resets their password             | deleted, unless the change was made with it | signed out, unless the change was made in this session |
| The account switched to can't sign in                              | refused (`401`) | signed out (`401`) |

These are checked on every request: `AuthServiceProvider` accepts a switched token only while its impersonator can sign
in, and [`AuthenticateSession`](03-sessions.md) compares the impersonator's password with the hash kept at the switch.
So a switched token or session never outlives the access of the user recorded as its impersonator.

## Acting as the account

- Requests act as the account switched to: in its organization, with its roles and permissions.
- [`password.updated`](10-required-password-update.md) checks the password of the user who switched, the person behind
  the request. A super admin may work in an account whose password must be replaced, and a person can't avoid their
  own requirement by switching to their linked account.
- [`change-password`](07-passwords.md) changes the password of the account's person and needs that person's current
  password, so a super admin can't change someone's password without knowing it.

## Events

Switching fires no `Login` event. With `api-core.audit.auth_events` on, the audit log records `switched` on the account
switched to, caused by the user who switched, with `metadata.impersonator_id` (`null` when a session switches back). A
`logout` of a switched account records the impersonator too. See [Recording events](../audit/03-recording-events.md).

## Errors

| Status | Field     | Message                                       |
|--------|-----------|-----------------------------------------------|
| `403`  |           | You may not switch to this account.           |
| `403`  |           | A switched account may not switch again.      |
| `422`  | `user_id` | You are already signed in to this account.    |
| `422`  | `user_id` | This account is suspended.                    |
| `422`  | `user_id` | The selected user id is invalid.              |

## Related

- [Linked accounts](06-linked-accounts.md)
- [Access and refresh tokens](02-tokens.md)
- [Sessions](03-sessions.md)
- [The signed-in user](04-me.md)
- [Roles overview](../roles/00-overview.md)
- [Auditing requests](../audit/02-auditing-requests.md)
