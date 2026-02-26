<?php

return [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'battlevue',
    'DB_USER' => 'battlevue_user',
    'DB_PASS' => 'change_me',

    'APP_ENV' => 'dev',
    'APP_BASE_URL' => 'https://battlevue.gops.app',

    'SESSION_COOKIE_NAME' => 'battlevue_session',
    'SESSION_TTL_SECONDS' => 1209600,

    'CSRF_SECRET' => 'replace_with_long_random_string',
    'PASSWORD_PEPPER' => '',

    'RATE_LIMIT_AUTH_WINDOW_SECONDS' => 900,
    'RATE_LIMIT_AUTH_MAX' => 20,
    'RATE_LIMIT_CHAT_WINDOW_SECONDS' => 60,
    'RATE_LIMIT_CHAT_MAX' => 30,

    'SIMULATOR_VERSION' => 'v1',
    'BUILD_COMMIT_SHA' => '',
    'INTERNAL_SIMULATE_KEY' => 'replace_internal_key',
];
