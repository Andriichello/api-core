<?php

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
         * The maximum number of login, refresh and password reset
         * attempts per minute for an email and IP address.
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
