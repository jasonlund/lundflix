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
        'client_identifier' => env('PLEX_CLIENT_IDENTIFIER').'-'.env('APP_ENV', 'production'),
        'product_name' => env('PLEX_PRODUCT_NAME', 'Lund'),
        'server_identifier' => env('PLEX_SERVER_IDENTIFIER'),
        'seed_token' => env('SEED_PLEX_TOKEN'),
    ],

    'fanart' => [
        'api_key' => env('FANART_API_KEY'),
    ],

];
