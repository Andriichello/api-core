<?php

use Core\Models\Organization;
use Core\Models\User;
use Spatie\Permission\Models\Role;

return [
    'auth' => [
        /*
         * Lifetime of an access token, which authenticates
         * requests, in minutes.
         */
        'access_token_lifetime' => (int) env('API_CORE_ACCESS_TOKEN_LIFETIME', 480),

        /*
         * Lifetime of a refresh token, which is exchanged once for
         * a new pair of access and refresh tokens, in minutes.
         */
        'refresh_token_lifetime' => (int) env('API_CORE_REFRESH_TOKEN_LIFETIME', 20160),

        /*
         * The maximum number of login, refresh, password reset and email
         * verification attempts per minute for an email and IP address.
         */
        'rate_limit' => (int) env('API_CORE_AUTH_RATE_LIMIT', 10),

        /*
         * Page of the SPA, to which the link in the password reset email
         * leads. The link adds `?token=…&email=…` to it, and the page posts
         * them with the new password to the `reset-password` route.
         */
        'password_reset_url' => env(
            'API_CORE_PASSWORD_RESET_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/reset-password'
        ),

        /*
         * Page of the SPA, to which the link in the email verification email
         * leads. The link adds `?id=…&hash=…&expires=…&signature=…` to it, and
         * the page posts them to the `verify-email` route. The link expires
         * in `auth.verification.expire` minutes (60 by default).
         */
        'email_verification_url' => env(
            'API_CORE_EMAIL_VERIFICATION_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/verify-email'
        ),

        /*
         * Page of the SPA, to which the link in an invitation email leads.
         * The link adds `?id=…&hash=…&expires=…&signature=…` to it, and the
         * page posts them, with the password, which the person chooses, to
         * the `accept-invitation` route.
         */
        'invitation_url' => env(
            'API_CORE_INVITATION_URL',
            rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/accept-invitation'
        ),

        /*
         * Lifetime of the link in an invitation email, in minutes.
         */
        'invitation_lifetime' => (int) env('API_CORE_INVITATION_LIFETIME', 10080),
    ],

    /*
     * Roles of users from the highest to the lowest, with the actions, which
     * each of them has on a resource (a model class): `viewAny`, `view`,
     * `create`, `update`, `delete`, `forceDelete`, custom ones, or `all` for
     * the first five. The order ranks them, so a user is higher than another,
     * whose roles are all lower. `super-admin` is api-core's own role above
     * all of them, which needs no permissions. It is a list, so the
     * application's list replaces this one as a whole, and
     * `RolesAndPermissionsSeeder` syncs the database with it.
     */
    'roles' => [
        [
            'name' => 'admin',
            'permissions' => [
                User::class => ['all', 'attachRole', 'attachAnyRole', 'detachRole'],
                Organization::class => ['viewAny', 'view', 'update'],
                Role::class => ['attachUser', 'attachAnyUser', 'detachUser'],
            ],
        ],
        [
            'name' => 'user',
            'permissions' => [
                User::class => ['viewAny', 'view'],
                Organization::class => ['viewAny', 'view'],
            ],
        ],
        [
            'name' => 'guest',
            'permissions' => [
                User::class => ['viewAny', 'view'],
                Organization::class => ['viewAny', 'view'],
            ],
        ],
    ],

    /*
     * Locales, which the `locale` middleware sets for requests: the user's
     * one, or else the best match of `Accept-Language`, or else the app's
     * default (`app.locale`). Their translations, such as `lang/uk/
     * validation.php`, are the application's. It is a list, so the
     * application's list replaces this one.
     */
    'locales' => ['en'],

    'http' => [
        /*
         * Paths, on which requests always expect JSON, as if they had
         * `Accept: application/json`, so their responses, errors included,
         * are JSON. An empty array turns it off.
         */
        'json_paths' => ['api', 'api/*'],
    ],

    'files' => [
        /*
         * Disk, on which the files of models (see HasFiles) are stored. It
         * should be a private one, since files are downloaded through the
         * API, which checks access to the model, which they are attached to.
         */
        'disk' => env('API_CORE_FILES_DISK', 'local'),

        /*
         * Directory on the disk, under which each model's files are stored
         * in `{morph}/{id}`, e.g. `files/models.users/1`.
         */
        'directory' => env('API_CORE_FILES_DIRECTORY', 'files'),

        /*
         * The maximum size of an uploaded file, in kilobytes.
         */
        'max_size' => (int) env('API_CORE_FILES_MAX_SIZE', 10240),

        /*
         * Extensions of the files, which may be uploaded. A file's extension
         * and the one, which its contents are recognized as, must both be in
         * the list. It is a list, so the application's list replaces this one.
         */
        'extensions' => [
            'pdf', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp',
            'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods',
        ],

        /*
         * Lifetime of a temporary URL of a file, in minutes. Only disks,
         * which provide temporary URLs (`s3`, or `local` with its `serve`
         * option), give files a URL.
         */
        'url_lifetime' => (int) env('API_CORE_FILES_URL_LIFETIME', 5),
    ],

    'audit' => [
        /*
         * Days, for which entries of the audit log are kept, before
         * `php artisan model:prune --model="Core\Models\AuditLog"` deletes
         * them. Null keeps them forever.
         */
        'retention_days' => env('API_CORE_AUDIT_RETENTION_DAYS') === null
            ? null : (int) env('API_CORE_AUDIT_RETENTION_DAYS'),

        /*
         * What the `audit` middleware records of a request, unless its
         * parameters name it (`audit:http,data`): `http` (the method, URL,
         * route parameters, status, IP address, user agent and duration),
         * `data` (the payload with the `scrub_fields` masked) and `reads`
         * (successful GET requests too). Errors are always recorded, with
         * the response and the exception. It is a list, so the
         * application's list replaces this one.
         */
        'scopes' => ['http'],

        /*
         * Requests, which the `audit` middleware doesn't record: by their
         * paths (`$request->is()`) or route names (`$request->routeIs()`).
         */
        'except' => [
            'paths' => [],
            'names' => ['sanctum.csrf-cookie'],
        ],

        /*
         * The maximum size of a payload, and of an error response, which
         * the audit log keeps, in bytes. A larger payload is left out, and
         * a larger response is cut.
         */
        'max_payload_size' => (int) env('API_CORE_AUDIT_MAX_PAYLOAD_SIZE', 2048),

        /*
         * Fields of a payload, whose values are masked at any depth,
         * regardless of case.
         */
        'scrub_fields' => [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'access_token',
            'refresh_token',
            'remember_token',
            'secret',
            'client_secret',
            'code',
            'card_number',
            'cvv',
        ],
    ],
];
