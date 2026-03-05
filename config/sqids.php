<?php

return [
    'alphabet' => env('SQIDS_ALPHABET', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
    'min_length' => (int) env('SQIDS_MIN_LENGTH', 8),
];
