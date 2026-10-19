# Passwords

A person has one password for all of their [linked accounts](06-linked-accounts.md). This document explains how it is
shared, how a signed-in user changes it with `POST /api/auth/change-password`, and how code should set it.

## One password per person

The password belongs to the person, not to an account:

- `User::getAuthPassword()` returns the main account's password hash, even when the main account is soft-deleted, so
  every login and every `current_password` check of a linked account is made against the person's password.
- `Core\Models\Traits\SharesPasswordAcrossAccounts` sets a password, which is saved on any account, on the main account
  and every account linked to it, deleted ones included. An account takes the main account's password when it is
  linked. The time the person set it, `password_updated_at`, goes along with it.
- Each account holds a copy, so an account that becomes the main one, or is unlinked by an email change, keeps the
  person's password.

Why one password: linked accounts are one person, proven by the email verified on each of them. With one password the
person signs in once and [switches](05-switching-accounts.md) between organizations without another sign-in, and a
change or a reset of the password reaches all of their accounts, so an old password can't keep opening one of them.
Accounts that only share an email, but aren't linked, may belong to different people, so the password isn't kept per
email.

## Changing the password

`POST /api/auth/change-password` changes the password of the signed-in user, who knows the current one. It needs
`auth:sanctum` and is throttled.

| Field                   | Rules                                                                                  |
|-------------------------|----------------------------------------------------------------------------------------|
| `current_password`      | required, string, the person's current password                                        |
| `password`              | required, string, confirmed, different from `current_password`, `Password::defaults()` |
| `password_confirmation` | the same as `password`                                                                  |

```http
POST /api/auth/change-password
Authorization: Bearer 1|psg1ogx0XOjm3lwj62rDnyNDuZHdaW4Ha4RhmRT5c503a0e5
Content-Type: application/json

{
    "current_password": "secret-password",
    "password": "new-secret-password",
    "password_confirmation": "new-secret-password"
}
```

```json
{
    "message": "Your password has been changed."
}
```

`Password::defaults()` is the application's rule (a minimum of 8 characters unless the application sets its own in a
service provider). The new password must differ from the current one, so a password that someone else set, and which
must be [replaced](10-required-password-update.md), can't be kept by setting it again.

What a change does:

- The password is set on all of the person's accounts, as set by the person (`password_updated_at` is now), which lifts
  a [required password update](10-required-password-update.md).
- Every other token of the person's accounts is deleted, and so is every token with which they switched to other
  accounts. The token the request was made with stays.
- The person's other sessions are signed out on their next request by [`AuthenticateSession`](03-sessions.md), as their
  password hash no longer matches. The session the request was made in stays signed in, also when it is switched to a
  linked account.

The current password is checked against the password of the account the request acts as. A super admin who switched to
someone else's account can't change that person's password without knowing it.

## Errors

| Status | Field              | Message                                                        |
|--------|--------------------|----------------------------------------------------------------|
| `422`  | `current_password` | The password is incorrect.                                     |
| `422`  | `password`         | The password field confirmation does not match.                |
| `422`  | `password`         | The password field and current password must be different.     |
| `422`  | `password`         | The password field must be at least 8 characters.              |
| `401`  |                    | Unauthenticated.                                               |
| `429`  |                    | Too Many Attempts.                                             |

## Setting a password in code

`UserRepository::updatePassword($user, $password)` sets a password the person chose, as updated by them:

```php
use Core\Repositories\UserRepository;

app(UserRepository::class)->updatePassword($user, $request->string('password')->value());
```

Any other write of `password` without `password_updated_at`, such as `$user->forceFill(['password' => ...])->save()` by
an admin tool or a seeder, counts as set by someone else, and the person must replace it (see
[Required password update](10-required-password-update.md)). Both are shared with the person's other accounts.

## Related

- [Linked accounts](06-linked-accounts.md)
- [Password reset](08-password-reset.md)
- [Required password update](10-required-password-update.md)
- [Access and refresh tokens](02-tokens.md)
- [Sessions](03-sessions.md)
