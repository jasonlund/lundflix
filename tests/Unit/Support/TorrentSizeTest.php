<?php

declare(strict_types=1);

use App\Support\TorrentSize;

it('parses standard units', function (string $input, int $expected) {
    expect(TorrentSize::parse($input))->toBe($expected);
})->with([
    'bytes' => ['512 B', 512],
    'kilobytes' => ['1 KB', 1024],
    'megabytes' => ['1 MB', 1024 ** 2],
    'gigabytes' => ['1 GB', 1024 ** 3],
    'terabytes' => ['1 TB', 1024 ** 4],
]);

it('parses single-letter shorthand units', function (string $input, int $expected) {
    expect(TorrentSize::parse($input))->toBe($expected);
})->with([
    'K' => ['2 K', 2 * 1024],
    'M' => ['2 M', 2 * 1024 ** 2],
    'G' => ['2 G', 2 * 1024 ** 3],
    'T' => ['2 T', 2 * 1024 ** 4],
]);

it('tolerates whitespace and case', function (string $input) {
    expect(TorrentSize::parse($input))->toBe((int) round(4.31 * 1024 ** 3));
})->with([
    'space' => '4.31 GB',
    'no space' => '4.31GB',
    'lower' => '4.31 gb',
    'mixed' => '4.31 Gb',
    'padded' => '  4.31  gb  ',
]);

it('parses fractional IPT-style sizes', function () {
    expect(TorrentSize::parse('850.42 MB'))->toBe((int) round(850.42 * 1024 ** 2));
});

it('throws on unparseable input', function (string $input) {
    TorrentSize::parse($input);
})->throws(InvalidArgumentException::class)->with([
    'empty' => '',
    'whitespace only' => '   ',
    'non-numeric' => 'foo',
    'unit only' => 'GB',
    'no unit' => '4.31',
    'unknown unit' => '4.31 XB',
    'multiple dots' => '4.3.1 GB',
]);

it('formats bytes using the largest fitting unit', function (int $bytes, string $expected) {
    expect(TorrentSize::format($bytes))->toBe($expected);
})->with([
    'bytes' => [512, '512 B'],
    'kilobytes' => [1024, '1.00 KB'],
    'megabytes' => [1024 ** 2, '1.00 MB'],
    'gigabytes' => [(int) round(4.31 * 1024 ** 3), '4.31 GB'],
    'terabytes' => [1024 ** 4, '1.00 TB'],
]);

it('round-trips representative IPT strings within rounding tolerance', function (string $input) {
    $bytes = TorrentSize::parse($input);
    $formatted = TorrentSize::format($bytes);

    expect(TorrentSize::parse($formatted))->toBe($bytes);
})->with([
    '4.31 GB',
    '850.42 MB',
    '15.00 GB',
    '1.00 TB',
]);

it('uses 1024-based units', function () {
    expect(TorrentSize::parse('1 KB'))->toBe(1024)
        ->and(TorrentSize::parse('1 MB'))->toBe(1048576)
        ->and(TorrentSize::parse('1 GB'))->toBe(1073741824);
});
