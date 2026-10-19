# Email verification

A signed-in user asks for a verification link with `POST /api/auth/email/verification-notification`; the link opens
the SPA, which posts its parameters to `POST /api/auth/verify-email`, signed in or not. A verified email lets
`verified` routes through and [links](06-linked-accounts.md) the account to the person's other accounts.

## What verification is for

- `Core\Models\User` implements Laravel's `MustVerifyEmail`, so Laravel's `verified` middleware works on the
  application's routes, answering `403` with "Your email address is not verified." until the email is verified.
- A verified email is what [links](06-linked-accounts.md) an account to the person's other accounts with that email.
- A changed email counts as unverified, unless it is verified in the same save, because the old verification doesn't
  prove the new address.

Accepting an [invitation](11-invitations.md) verifies the email too.

## Asking for a link

`POST /api/auth/email/verification-notification` needs `auth:sanctum`, is throttled and takes no body. It emails
Laravel's `VerifyEmail` notification to the signed-in account:

```json
{
    "message": "A link to verify your email has been sent to it."
}
```

When the email is verified already, nothing is sent:

```json
{
    "message": "Your email is already verified."
}
```

## The link

The link leads to the SPA's page, `api-core.auth.email_verification_url` (`API_CORE_EMAIL_VERIFICATION_URL`, by
default `{APP_URL}/verify-email`), with four parameters in its query:

```text
https://app.example.com/verify-email?id=1&hash=8b6a…&expires=1792530072&signature=5d0e…
```

| Parameter   | Description                                                                                         |
|-------------|-----------------------------------------------------------------------------------------------------|
| `id`        | The account's id                                                                                    |
| `hash`      | The SHA-1 hash of the account's email, so the link stops working when the email changes             |
| `expires`   | The Unix time when the link expires: `auth.verification.expire` minutes (60) after it was made      |
| `signature` | An HMAC-SHA256 of the other parameters and the link's purpose, made with the application's key      |

The link works without signing in: its arrival in the inbox is what proves that the person owns the email. The purpose
in the signature (`email-verification`) keeps a verification link from being valid for anything else, such as an
invitation. Links signed with a previous key (`APP_PREVIOUS_KEYS`) keep working after the key is rotated. The
parameters come from `Core\Http\Requests\Traits\WithAccountLink` (see
[Request traits](../http/requests/03-request-traits.md)).

An application that wants another link replaces it in the `boot()` method of its own service provider:

```php
use Core\Http\Requests\Auth\VerifyEmailRequest;
use Illuminate\Auth\Notifications\VerifyEmail;

VerifyEmail::createUrlUsing(
    fn ($user) => 'https://app.example.com/email/verify?' . http_build_query(VerifyEmailRequest::linkParameters($user))
);
```

## Verifying

| Field       | Rules                     |
|-------------|---------------------------|
| `id`        | required, integer         |
| `hash`      | required, string, max 255 |
| `expires`   | required, integer         |
| `signature` | required, string, max 255 |

```http
POST /api/auth/verify-email
Content-Type: application/json

{"id": 1, "hash": "8b6a…", "expires": 1792530072, "signature": "5d0e…"}
```

```json
{
    "message": "Your email has been verified."
}
```

What a verification does:

- It sets `email_verified_at` and fires Laravel's `Illuminate\Auth\Events\Verified`.
- When it links the account to another account with the same verified email (see
  [Linked accounts](06-linked-accounts.md) for the rules), the account takes that person's password, and the route
  answers "Your email has been verified, and the account now signs in with the password of your other accounts." Its
  tokens, and the tokens with which it switched to other accounts, are deleted, as they were issued with its old
  password.
- A link used again answers "Your email is already verified." and changes nothing.

## Errors

| Status | Field     | Message                                                            |
|--------|-----------|--------------------------------------------------------------------|
| `403`  |           | The verification link is invalid or has expired.                   |
| `422`  | a parameter | The usual validation messages for missing parameters             |
| `401`  |           | Unauthenticated. (asking for a link without signing in)             |
| `429`  |           | Too Many Attempts.                                                 |

A link is invalid when its signature doesn't match its parameters, it has expired, its account doesn't exist, or the
account's email has changed since it was made. Asking for links counts towards the `api-core-auth` limit of the
signed-in user's email; verifying, which has no email, counts towards the limit of the IP address.

## Related

- [Linked accounts](06-linked-accounts.md)
- [Invitations](11-invitations.md)
- [Overview](00-overview.md)
- [Request traits](../http/requests/03-request-traits.md)
- [Configuration](../getting-started/03-configuration.md)
- [Translations](../localization/01-translations.md)
