<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'enabled' => env('SLACK_ENABLED', false),
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'plex' => [
        'client_identifier' => env('PLEX_CLIENT_IDENTIFIER'),
        'product_name' => env('PLEX_PRODUCT_NAME', 'lundflix'),
        'server_identifier' => env('PLEX_SERVER_IDENTIFIER'),
        'seed_token' => env('SEED_PLEX_TOKEN'),
        'webhook_secret' => env('PLEX_WEBHOOK_SECRET'),
        'webhook_debounce_seconds' => (int) env('PLEX_WEBHOOK_DEBOUNCE_SECONDS', 30),
        'webhook_max_batch_seconds' => (int) env('PLEX_WEBHOOK_MAX_BATCH_SECONDS', 3600),
        'webhook_added_at_max_age_minutes' => (int) env('PLEX_WEBHOOK_ADDED_AT_MAX_AGE_MINUTES', 15),
        'webhook_queue' => env('PLEX_WEBHOOK_QUEUE', 'plex-webhooks'),
        'webhook_cache_store' => env('PLEX_WEBHOOK_CACHE_STORE', 'redis'),
    ],

    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
    ],

    'predb' => [
        'base_url' => env('PREDB_BASE_URL', 'https://api.predb.net'),
    ],

    'iptorrents' => [
        'base_url' => env('IPT_BASE_URL', 'https://iptorrents.com'),
    ],

];
