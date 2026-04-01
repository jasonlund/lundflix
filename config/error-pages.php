<?php

return [
    401 => [
        'message' => 'Unauthorized',
        'description' => 'You need to be logged in to access this page.',
        'videos' => [
            [
                'video' => 'errors/401_seinfeld.webm',
                'type' => 'show',
                'imdb_id' => 'tt0098904',
            ],
        ],
    ],
    403 => [
        'message' => 'Forbidden',
        'description' => 'You don\'t have permission to access this page.',
        'videos' => [
            [
                'video' => 'errors/403_the_white_lotus.webm',
                'type' => 'show',
                'imdb_id' => 'tt13406094',
                'caption' => ['The pineapple suite', 'is occupied'],
            ],
        ],
    ],
    404 => [
        'message' => 'Not Found',
        'description' => 'The page you\'re looking for doesn\'t exist.',
        'videos' => [
            [
                'video' => 'errors/404_lost.webm',
                'type' => 'show',
                'imdb_id' => 'tt0411008',
            ],
        ],
    ],
    419 => [
        'message' => 'Page Expired',
        'description' => 'Your session has expired. Please refresh and try again.',
        'videos' => [
            [
                'video' => 'errors/419_pushing_daisies.webm',
                'type' => 'show',
                'imdb_id' => 'tt0925266',
            ],
        ],
    ],
    500 => [
        'message' => 'Internal Server Error',
        'description' => 'Something went wrong on our end. Please try again later.',
        'videos' => [
            [
                'video' => 'errors/500_jericho.webm',
                'type' => 'show',
                'imdb_id' => 'tt0805663',
            ],
        ],
    ],
    503 => [
        'message' => 'Service Unavailable',
        'description' => 'We\'re temporarily down for maintenance. Please check back shortly.',
        'videos' => [
            [
                'video' => 'errors/503_ncis.webm',
                'type' => 'show',
                'imdb_id' => 'tt0364845',
            ],
        ],
    ],
];
