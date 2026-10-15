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

    'http' => [
        /*
         * Paths, on which requests always expect JSON, as if they had
         * `Accept: application/json`, so their responses, errors included,
         * are JSON. An empty array turns it off.
         */
        'json_paths' => ['api', 'api/*'],
    ],
];
