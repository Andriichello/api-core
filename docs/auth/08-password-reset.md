# Password reset

A person who forgot their password asks for a link with `POST /api/auth/forgot-password`, opens it on the SPA, and
sets a new password with `POST /api/auth/reset-password`. Neither route needs signing in.

## Asking for a link

| Field   | Rules                             |
|---------|-----------------------------------|
| `email` | required, string, email, max 255  |

```http
POST /api/auth/forgot-password
Content-Type: application/json

{"email": "ann@example.com"}
```

```json
{
    "message": "If the email belongs to an account, a link to reset the password has been sent to it."
}
```

The answer is the same whether the email belongs to an account or not, whether the account is deleted, and whether a
link was sent to the email shortly before, so the route doesn't tell which emails have accounts. Laravel's password
broker sends Laravel's `ResetPassword` notification and sends no new link within `auth.passwords.users.throttle`
seconds (60) of the last one.

## The link

The emailed link leads to the SPA's page, `api-core.auth.password_reset_url` (`API_CORE_PASSWORD_RESET_URL`, by default
`{APP_URL}/reset-password`), with the token and the email in its query:

```text
https://app.example.com/reset-password?token=6f1c…&email=ann%40example.com
```

The page asks for the new password and posts it with the token and the email to `reset-password`. The token works once,
for `auth.passwords.users.expire` minutes (60). An application that wants another link replaces it in the `boot()`
method of its own service provider, which runs after api-core's:

```php
use Illuminate\Auth\Notifications\ResetPassword;

ResetPassword::createUrlUsing(
    fn ($user, string $token) => 'https://app.example.com/password/' . $token
        . '?email=' . urlencode($user->getEmailForPasswordReset())
);
```

## Resetting the password

| Field                   | Rules                                                   |
|-------------------------|---------------------------------------------------------|
| `token`                 | required, string, max 255                               |
| `email`                 | required, string, email, max 255; matched in lowercase  |
| `password`              | required, string, confirmed, `Password::defaults()`     |
| `password_confirmation` | the same as `password`                                  |

```http
POST /api/auth/reset-password
Content-Type: application/json

{
    "token": "6f1c…",
    "email": "ann@example.com",
    "password": "new-secret-password",
    "password_confirmation": "new-secret-password"
}
```

```json
{
    "message": "Your password has been reset."
}
```

A reset, in one transaction:

- sets the password on all accounts, deleted ones included, of every person who has an account with the email, as
  set by the person (`password_updated_at` is now), which lifts a
  [required password update](10-required-password-update.md);
- deletes every token of those accounts, and the tokens with which those people switched to other accounts;
- fires Laravel's `Illuminate\Auth\Events\PasswordReset` once for each person.

Their sessions are signed out on their next request by [`AuthenticateSession`](03-sessions.md), as the password hash
they keep no longer matches.

Why every person with the email: the same email may have accounts of different people, in organizations where they
aren't [linked](06-linked-accounts.md). The reset token is sent to the email and proves only that the requester
controls it, and whoever controls the email may reset the password of any account with it. So the reset sets the
password of all of them at once.

## Errors

| Status | Field      | Message                                                            |
|--------|------------|--------------------------------------------------------------------|
| `422`  | `email`    | This password reset token is invalid.                              |
| `422`  | `password` | The password field confirmation does not match.                    |
| `422`  | `password` | The password field must be at least 8 characters.                  |
| `422`  | any field  | The usual validation messages for missing fields                   |
| `429`  |            | Too Many Attempts.                                                 |

A wrong, used or expired token and an unknown email all get "This password reset token is invalid.", so the error
doesn't tell which emails have accounts either. Both routes count towards the `api-core-auth` limit of the email and
IP address, which they share with `login`.

## Related

- [Passwords](07-passwords.md)
- [Required password update](10-required-password-update.md)
- [Access and refresh tokens](02-tokens.md)
- [Sessions](03-sessions.md)
- [Configuration](../getting-started/03-configuration.md)
- [Translations](../localization/01-translations.md)
