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
            'library_channel' => env('SLACK_LIBRARY_CHANNEL'),
        ],
    ],

    'plex' => [
        'client_identifier' => env('PLEX_CLIENT_IDENTIFIER'),
        'product_name' => env('PLEX_PRODUCT_NAME', 'lundflix'),
        'server_identifier' => env('PLEX_SERVER_IDENTIFIER'),
        'seed_token' => env('SEED_PLEX_TOKEN'),
        'poll_initial_lookback_seconds' => (int) env('PLEX_POLL_INITIAL_LOOKBACK_SECONDS', 300),
        'poll_debounce_seconds' => (int) env('PLEX_POLL_DEBOUNCE_SECONDS', 300),
        'poll_hard_deadline_seconds' => (int) env('PLEX_POLL_HARD_DEADLINE_SECONDS', 900),
    ],

    'tmdb' => [
        'api_key' => env('TMDB_API_KEY'),
    ],

    'iptorrents' => [
        'base_url' => env('IPT_BASE_URL', 'https://iptorrents.com'),
    ],

];
