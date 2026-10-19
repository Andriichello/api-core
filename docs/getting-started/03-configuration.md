# Configuration

Every option of `config/api-core.php` and `config/pagination.php`, and the options of Laravel's `config/auth.php` and
Sanctum, which api-core relies on. Most can be set with environment variables, without publishing anything.

## How the config is merged

`CoreServiceProvider` merges api-core's files into the application's config:

- **`config/api-core.php`** is merged recursively. Nested options merge key by key, so the application's file holds
  only the options it changes. **Lists replace api-core's lists as a whole**, even an empty one: `roles`, `locales`,
  `http.json_paths`, `files.extensions`, `audit.scopes`, `audit.except.paths`, `audit.except.names` and
  `audit.scrub_fields`.
- **`config/pagination.php`** is merged with Laravel's `mergeConfigFrom()`: each top-level key of the application's
  file wins.

Publish both files with `php artisan vendor:publish --tag=api-core-config`, or write a smaller file of your own:

```php
// config/api-core.php
return [
    'locales' => ['en', 'uk'],

    'files' => [
        'max_size' => 20480,
    ],
];
```

Here `locales` replaces `['en']`, while `files` keeps its other options. With `php artisan config:cache`, the merge
happens when the cache is built, so rebuild it after changing the config or upgrading api-core.

## Authentication

| Key                                  | Env                               | Default                       | Description                                                                                 |
|--------------------------------------|-----------------------------------|-------------------------------|---------------------------------------------------------------------------------------------|
| `api-core.auth.access_token_lifetime`  | `API_CORE_ACCESS_TOKEN_LIFETIME`  | `480`                         | Minutes, for which an access token authenticates requests.                                  |
| `api-core.auth.refresh_token_lifetime` | `API_CORE_REFRESH_TOKEN_LIFETIME` | `20160`                       | Minutes, within which a refresh token can be exchanged (once) for new tokens.               |
| `api-core.auth.rate_limit`             | `API_CORE_AUTH_RATE_LIMIT`        | `10`                          | Requests per minute to the throttled auth routes, per email and IP address.                 |
| `api-core.auth.password_reset_url`     | `API_CORE_PASSWORD_RESET_URL`     | `{APP_URL}/reset-password`    | SPA page of the password reset link, which adds `?token=…&email=…`.                         |
| `api-core.auth.email_verification_url` | `API_CORE_EMAIL_VERIFICATION_URL` | `{APP_URL}/verify-email`      | SPA page of the verification link, which adds `?id=…&hash=…&expires=…&signature=…`.         |
| `api-core.auth.invitation_url`         | `API_CORE_INVITATION_URL`         | `{APP_URL}/accept-invitation` | SPA page of the invitation link, with the same parameters.                                  |
| `api-core.auth.invitation_lifetime`    | `API_CORE_INVITATION_LIFETIME`    | `10080`                       | Minutes, for which an invitation link works (7 days).                                       |

The rate limiter `api-core-auth` throttles `login`, `refresh`, `forgot-password`, `reset-password`, `verify-email`,
`accept-invitation`, `email/verification-notification` and `change-password`. A request without an `email` counts for
the signed-in user's email. See [Authentication](../auth/00-overview.md) and [Tokens](../auth/02-tokens.md).

## Roles, locales and HTTP

| Key                       | Env | Default              | Description                                                                                         |
|---------------------------|-----|----------------------|-----------------------------------------------------------------------------------------------------|
| `api-core.roles`          | –   | `admin`, `user`, `guest` | Roles from the highest to the lowest, with their permissions. See [Configuring roles](../roles/01-configuring-roles.md). |
| `api-core.locales`        | –   | `['en']`             | Locales, which the `locale` middleware may set. See [Locale middleware](../localization/00-locale-middleware.md). |
| `api-core.http.json_paths` | –  | `['api', 'api/*']`   | Paths, whose requests always get JSON, errors included; `[]` turns it off. See [Errors](../http/02-errors.md). |

## Files

| Key                         | Env                          | Default   | Description                                                                                   |
|-----------------------------|------------------------------|-----------|-----------------------------------------------------------------------------------------------|
| `api-core.files.disk`         | `API_CORE_FILES_DISK`        | `local`   | Disk of the files of models; keep it private, as downloads go through the API.               |
| `api-core.files.directory`    | `API_CORE_FILES_DIRECTORY`   | `files`   | Directory on the disk; a model's files go to `{directory}/{morph}/{id}`.                     |
| `api-core.files.max_size`     | `API_CORE_FILES_MAX_SIZE`    | `10240`   | Maximum size of an upload, in kilobytes.                                                     |
| `api-core.files.extensions`   | –                            | `pdf`, `txt`, `csv`, `jpg`, `jpeg`, `png`, `gif`, `webp`, `doc`, `docx`, `xls`, `xlsx`, `odt`, `ods` | Extensions allowed for uploads, both by name and by contents. |
| `api-core.files.url_lifetime` | `API_CORE_FILES_URL_LIFETIME` | `5`      | Minutes, for which a temporary URL of a file works.                                          |

See [Upload validation](../files/03-upload-validation.md) and [Downloads and URLs](../files/04-downloads-and-urls.md).

## Audit log

| Key                               | Env                               | Default                   | Description                                                                 |
|-----------------------------------|-----------------------------------|---------------------------|-----------------------------------------------------------------------------|
| `api-core.audit.retention_days`   | `API_CORE_AUDIT_RETENTION_DAYS`   | `null`                    | Days, for which entries are kept before pruning; `null` keeps them forever. |
| `api-core.audit.auth_events`      | `API_CORE_AUDIT_AUTH_EVENTS`      | `false`                   | Records `login`, `login_failed`, `logout` and `switched`.                   |
| `api-core.audit.scopes`           | –                                 | `['http']`                | What the `audit` middleware records: `http`, `data`, `reads`.               |
| `api-core.audit.except.paths`     | –                                 | `[]`                      | Paths (`$request->is()`), which the `audit` middleware skips.               |
| `api-core.audit.except.names`     | –                                 | `['sanctum.csrf-cookie']` | Route names (`$request->routeIs()`), which it skips.                        |
| `api-core.audit.max_payload_size` | `API_CORE_AUDIT_MAX_PAYLOAD_SIZE` | `2048`                    | Bytes of a payload or an error response, which an entry keeps.              |
| `api-core.audit.scrub_fields`     | –                                 | `password`, `password_confirmation`, `current_password`, `token`, `access_token`, `refresh_token`, `remember_token`, `secret`, `client_secret`, `code`, `card_number`, `cvv` | Payload fields masked at any depth, regardless of case. |

See [Auditing requests](../audit/02-auditing-requests.md) and [Reading and pruning](../audit/04-reading-and-pruning.md).

## Pagination

`config/pagination.php` has no environment variables.

| Key                                | Default | Description                                                         |
|------------------------------------|---------|---------------------------------------------------------------------|
| `pagination.max_results`           | `1000`  | The largest page size; a larger requested size is cut to it.        |
| `pagination.default_size`          | `100`   | The page size, when a request asks for none (or for an invalid one). |
| `pagination.number_parameter`      | `number` | Key of the page number in `page[…]`.                               |
| `pagination.size_parameter`        | `size`  | Key of the page size in `page[…]`.                                  |
| `pagination.use_simple_pagination` | `false` | Paginates without counting the total (`total` and `last_page` are `null`). |
| `pagination.base_url`              | `null`  | Base URL of the page links, instead of the request's.               |
| `pagination.pagination_parameter`  | `page`  | Name of the pagination query parameter.                             |

See [Paginating lists](../pagination/00-paginating-lists.md).

## Laravel's auth config and Sanctum

api-core doesn't merge `config/auth.php`; the copy in its repository configures its own tests. In the application's
`config/auth.php`:

- `providers.users.model` must be `Core\Models\User`, which `AUTH_MODEL=Core\Models\User` sets.
- `guards.web` (the session guard) is the one, which Sanctum's `sanctum.guard` (`['web']`) signs a SPA's sessions in to.
- `passwords.users` is the password broker of `forgot-password` and `reset-password`: `table`
  (`password_reset_tokens`), `expire` (minutes of a reset link, `60`) and `throttle` (seconds between links, `60`).
- `verification.expire` sets the minutes of an email verification link (`60` when missing). Laravel's file doesn't
  have it, so add `'verification' => ['expire' => 60]` to change it.

Of Sanctum's config, `sanctum.stateful` (`SANCTUM_STATEFUL_DOMAINS`) lists the SPA's domains (see
[SPA with sessions](04-spa.md)). Leave `sanctum.expiration` at `null`: api-core gives each access token its own
`expires_at`, and a Sanctum expiration would also end tokens by their age.

## Related

- [Installation](02-installation.md)
- [Scheduled tasks](05-scheduled-tasks.md)
- [Configuring roles](../roles/01-configuring-roles.md)
- [Tokens](../auth/02-tokens.md)
- [Files](../files/00-overview.md)
- [Audit log](../audit/00-overview.md)
