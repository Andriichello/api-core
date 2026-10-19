# Locale middleware

The `locale` middleware (`Core\Http\Middleware\SetLocale`) sets the locale of each request, so its messages, such as
validation errors, come in the user's language. Use it on the API routes of an app, which supports more than one
locale.

## Setup

List the locales the app supports in its `config/api-core.php`, and add the middleware to the API routes, for example
to the `api` group in `bootstrap/app.php`:

```php
'locales' => ['en', 'uk'],
```

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: ['locale']);
})
```

The app's list replaces api-core's (`['en']`) as a whole. The translations of the locales are the app's own (see
[Translations](01-translations.md)).

## How the locale is chosen

1. The signed-in user's `locale`, when it is one of `api-core.locales`.
2. Otherwise the best match of the `Accept-Language` header among `app.locale` and `api-core.locales`. A region
   matches its language, so `uk-UA` gives `uk` and `en-GB` gives `en`.
3. Otherwise `app.locale` (`APP_LOCALE`): when the header is missing or empty, or matches none of the locales.

The middleware sets the locale with `App::setLocale()` for the rest of the request, and names it in the response's
`Content-Language` header (with a dash, so `pt_BR` is named `pt-BR`).

```http
GET /api/invoices
Authorization: Bearer {access-token}
Accept-Language: de-DE,de;q=0.9,uk;q=0.5
```

```http
HTTP/1.1 200 OK
Content-Language: uk
```

With `api-core.locales` set to `['en', 'uk']`, this request gets `uk`, unless the user's own locale is `en`, which wins
over the header.

## Order of the middleware

api-core gives the middleware a priority right after `organization`, which comes right after authentication, so it
knows the user, wherever a route lists it, even before `auth:sanctum`. The priority orders route middleware only, so put
`locale` on routes or in a route group such as `api`, not among the global middleware, which run before authentication.

On a route without authentication, such as `login`, there is usually no user yet, so the header decides.

## The user's locale

Each account has a `locale` column (up to 5 characters, `en` by default), which the user changes with `PATCH auth/me`
and a manager with the user routes. It isn't checked against `api-core.locales`: a locale the app doesn't support is
kept, but skipped by the middleware, which then goes by the header.

## Configuration

| Key | Env | Default | Description |
|---|---|---|---|
| `api-core.locales` | | `['en']` | Locales the middleware sets. |
| `app.locale` | `APP_LOCALE` | `en` | The locale, when nothing else matches. A header, which asks for it, matches it, even when it isn't listed. |

## Related

- [Translations](01-translations.md)
- [Middleware](../http/03-middleware.md)
- [`GET` and `PATCH` `auth/me`](../auth/04-me.md)
- [Managing users](../users/01-managing-users.md)
- [Configuration](../getting-started/03-configuration.md)
