# Invitations

A manager invites a person by email; api-core makes an account the person can't use yet and emails them a link. The
person opens it on the SPA and accepts with `POST /api/auth/accept-invitation`, choosing their password, or keeping the
one of their other accounts.

## Inviting

Managers invite people through `UserController::routes()`: `POST /api/users` invites, and
`POST /api/users/{id}/invitation` sends the invitation again (`409` once it is accepted). Inviting the email of a
deleted user of the organization restores that user. See [Managing users](../users/01-managing-users.md) and
[Re-inviting deleted users](../users/02-re-inviting-deleted-users.md).

In code, `UserRepository::invite()` makes the account and sends the invitation, and `sendInvitation()` sends it again:

```php
use Core\Enums\UserRole;
use Core\Repositories\UserRepository;

$user = app(UserRepository::class)->invite(
    ['name' => 'Ann', 'email' => 'ann@example.com'],
    $organization->id,
    [UserRole::User],
    $request->user(), // the inviter, named in the email
);

app(UserRepository::class)->sendInvitation($user, $request->user());
```

The invited account has an unverified email and a random password nobody knows, so it can't sign in until the
invitation is accepted. A `password` among the attributes is ignored: the person chooses their own, so nobody else ever
knows it.

## The invitation email

`Core\Notifications\InvitationNotification` is sent by mail. With an inviter and an organization named Globex, it reads:

- Subject: "You are invited to Globex"
- "Grace invited you to join Globex." (without an inviter: "You are invited to join Globex.")
- A button, "Accept invitation", with the link
- "The link expires in 1 week."

An account without an organization is invited to the application, named by `app.name`. The strings go through `__()`,
so the application translates them (see [Translations](../localization/01-translations.md)).

## The link

The link leads to the SPA's page, `api-core.auth.invitation_url` (`API_CORE_INVITATION_URL`, by default
`{APP_URL}/accept-invitation`), with the parameters `id`, `hash`, `expires` and `signature`, as an
[email verification link](09-email-verification.md) has. It works for `api-core.auth.invitation_lifetime` minutes
(`API_CORE_INVITATION_LIFETIME`, 10080: a week), and is signed for the purpose `invitation`, so it can't verify an
email and a verification link can't accept an invitation.

Its `hash` covers the account's email and password hash. Accepting sets the password, so a link works once. Sending
the invitation again makes a new link, while the earlier ones keep working until they expire or one of them is used.

## Accepting

| Field                   | Rules                                                     |
|-------------------------|-----------------------------------------------------------|
| `id`, `hash`, `expires`, `signature` | The link's parameters, as in [`verify-email`](09-email-verification.md) |
| `password`              | nullable, string, confirmed, `Password::defaults()`       |
| `password_confirmation` | the same as `password`                                    |

```http
POST /api/auth/accept-invitation
Content-Type: application/json

{
    "id": 7,
    "hash": "3f2a…",
    "expires": 1793048472,
    "signature": "9c41…",
    "password": "chosen-password",
    "password_confirmation": "chosen-password"
}
```

```json
{
    "message": "Your invitation has been accepted, so you can sign in with your password."
}
```

Accepting, in one transaction:

- verifies the email, which [links](06-linked-accounts.md) the account to the person's other accounts with the same
  verified email, if there are any, and fires Laravel's `Illuminate\Auth\Events\Verified`;
- with a `password`, sets it on all of the person's accounts, as set by the person, and deletes all of their tokens, as
  a [reset](08-password-reset.md) would;
- without a `password`, keeps the password of the person's other accounts, which the linked account has taken, and
  their tokens, answering "Your invitation has been accepted, so you can sign in with the password of your other
  accounts." This works only when the account was linked: a person without other accounts must choose a password, or
  the request is refused with `422` and nothing changes, the email staying unverified.

A person with other accounts may leave the password out because they have [one password](07-passwords.md) for all of
their accounts already. Choosing one would change it everywhere; leaving it out lets them join without that. Then they
[sign in](01-login.md) to their main account and [switch](05-switching-accounts.md) to the new one.

## Errors

| Status | Field       | Message                                                          |
|--------|-------------|------------------------------------------------------------------|
| `403`  |             | The invitation link is invalid, has expired or has been used.    |
| `422`  | `password`  | The password field is required.                                  |
| `422`  | `password`  | The password field confirmation does not match.                  |
| `422`  | `password`  | The password field must be at least 8 characters.                |
| `422`  | a parameter | The usual validation messages for missing link parameters       |
| `429`  |             | Too Many Attempts.                                               |

The route needs no sign-in and counts towards the `api-core-auth` limit of the IP address.

## Related

- [Managing users](../users/01-managing-users.md)
- [Re-inviting deleted users](../users/02-re-inviting-deleted-users.md)
- [Email verification](09-email-verification.md)
- [Linked accounts](06-linked-accounts.md)
- [Passwords](07-passwords.md)
- [Required password update](10-required-password-update.md)
- [Translations](../localization/01-translations.md)
