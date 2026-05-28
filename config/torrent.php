<?php

declare(strict_types=1);

return [
    'max_bytes' => [
        'movie' => (int) env('TORRENT_MAX_MOVIE_BYTES', 15 * 1024 ** 3),
        'episode' => (int) env('TORRENT_MAX_EPISODE_BYTES', 4 * 1024 ** 3),
        'pack_per_episode' => (int) env('TORRENT_MAX_PACK_BYTES_PER_EPISODE', 4 * 1024 ** 3),
        'pack_absolute' => (int) env('TORRENT_MAX_PACK_BYTES_ABSOLUTE', 60 * 1024 ** 3),
    ],
];
