# Translations

api-core ships no translations: it has no `lang` directory, and translating its messages into the app's languages is
left to the app by design. This page lists which of its strings go through Laravel's translator, so an app can
translate them, and which stay in English.

## Why the app owns them

An app decides which languages it supports and how its messages are worded; a package can't know either. api-core's
translatable strings are Laravel's own keys (`validation.*`, `auth.failed`, `passwords.*`) and plain JSON strings,
with no package namespace, so the app's `lang` directory translates them along with Laravel's, in one place.

## Translatable strings

| Strings | Used by | Translated in |
|---|---|---|
| Validation messages, such as `The file field is required.` | Every request, which validates input | `lang/{locale}/validation.php` |
| `auth.failed` | `POST /api/auth/login` with wrong credentials | `lang/{locale}/auth.php` |
| `passwords.token`, `passwords.reset` | `POST /api/auth/reset-password` | `lang/{locale}/passwords.php` |
| `validation.required` for a missing `password` | `POST /api/auth/accept-invitation` | `lang/{locale}/validation.php` |
| The invitation email: `You are invited to :place`, `:inviter invited you to join :place.`, `You are invited to join :place.`, `Accept invitation`, `The link expires in :lifetime.` | Inviting a user | `lang/{locale}.json` |
| Laravel's password reset and email verification emails, and its mail layout (`Hello!`, `Regards,`) | `forgot-password`, sending a verification email | `lang/{locale}.json` |

In the invitation email, `:place` is the name of the account's organization (or `app.name` without one), `:inviter`
the name of the user, who invited, and `:lifetime` the link's lifetime in words, made by Carbon from
`api-core.auth.invitation_lifetime`.

## Adding a language

1. Add the locale to `api-core.locales` and put the `locale` middleware on the API routes (see
   [Locale middleware](00-locale-middleware.md)).
2. Run `php artisan lang:publish` to get Laravel's English files in `lang/en` as a starting point, and translate them
   into `lang/uk/validation.php`, `lang/uk/auth.php` and `lang/uk/passwords.php`.
3. Translate the JSON strings in `lang/uk.json`:

```json
{
    "You are invited to :place": "Вас запрошено до :place",
    ":inviter invited you to join :place.": ":inviter запрошує вас приєднатися до :place.",
    "You are invited to join :place.": "Вас запрошено приєднатися до :place.",
    "Accept invitation": "Прийняти запрошення",
    "The link expires in :lifetime.": "Посилання діє :lifetime."
}
```

A string without a translation is shown in English, since a JSON string's key is its English text.

Validation messages name the field, such as `invoice.numbering.strategy` for a setting. The `attributes` of
`lang/{locale}/validation.php` give fields readable names:

```php
'attributes' => [
    'invoice.numbering.strategy' => 'numbering of invoices',
],
```

## The locale of emails

An email to a user is rendered in the user's own `locale`, when it is one of `api-core.locales`: `User` implements
Laravel's `HasLocalePreference` (`preferredLocale()`), so the invitation is in the locale given to the invited person,
not in the manager's, and the password reset email in the person's, not in the one of the `forgot-password` request.
A locale, which isn't supported, gives `null`, and the email is rendered in the locale of the request, which sends it.

## Strings that stay in English

api-core's own messages aren't passed through the translator, so they stay in English in every locale:

- the `message` of successful responses: `OK`, `Created`, `Updated`, `Deleted`, `Restored`, `Invited`,
  `Invitation sent`, `Logged out`;
- its error messages, such as `Not found.`, `The stored file is missing.`, `The request has no fields to update.`,
  `This account is suspended.`, `The email is used in several organizations, so choose one of them.` and
  `The theme setting doesn't exist.`, and the errors of filters, sorts, includes and appends;
- the answer of `forgot-password`:
  `If the email belongs to an account, a link to reset the password has been sent to it.`

A client, which shows messages in the user's language, should go by the status codes and the keys of the errors
(`email`, `fields`, `settings`, …) for these, and word the messages itself.

## Related

- [Locale middleware](00-locale-middleware.md)
- [Invitations](../auth/11-invitations.md)
- [Password reset](../auth/08-password-reset.md)
- [Email verification](../auth/09-email-verification.md)
- [Login](../auth/01-login.md)
- [Errors](../http/02-errors.md)
