<?php

use App\Exceptions\IptorrentsAuthException;
use App\Exceptions\IptorrentsRateLimitExceededException;
use App\Models\Movie;
use App\Services\Torrent\FinderResult;
use App\Services\Torrent\Kind;
use App\Services\Torrent\TorrentFinder;
use App\Services\Torrent\TorrentRequest;
use App\Services\Torrent\TorrentResolver;

function makeResolverTorrentResult(string $size = '4 GB', int $id = 100): array
{
    return [
        'torrent_id' => $id,
        'name' => 'Some.Result.1080p',
        'size' => $size,
        'seeders' => 50,
        'leechers' => 5,
        'snatches' => 100,
        'uploaded' => 'now',
        'download_url' => 'https://iptorrents.com/download.php/100/x.torrent',
    ];
}

function makeMovieRequest(int $maxBytes = 15_000_000_000): TorrentRequest
{
    $movie = new Movie(['imdb_id' => 'tt1234567', 'title' => 'Test', 'year' => 2024]);

    return new TorrentRequest(Kind::Movie, $movie, $maxBytes);
}

function makeFinder(callable $supports, callable $find): TorrentFinder
{
    return new class($supports, $find) implements TorrentFinder
    {
        public function __construct(public $supportsFn, public $findFn) {}

        public function supports(TorrentRequest $request): bool
        {
            return ($this->supportsFn)($request);
        }

        public function findDetailed(TorrentRequest $request): FinderResult
        {
            $match = ($this->findFn)($request);

            return $match === null ? FinderResult::none() : FinderResult::hit($match);
        }
    };
}

it('returns first matching finder result', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 1)),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('6 GB', 2)),
    ]);

    expect($resolver->resolve(makeMovieRequest())['torrent_id'])->toBe(1);
});

it('skips finder when supports returns false', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => false, fn () => makeResolverTorrentResult('5 GB', 1)),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('6 GB', 2)),
    ]);

    expect($resolver->resolve(makeMovieRequest())['torrent_id'])->toBe(2);
});

it('falls through to next finder on null', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => null),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 99)),
    ]);

    expect($resolver->resolve(makeMovieRequest())['torrent_id'])->toBe(99);
});

it('returns null when all finders return null', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => null),
        makeFinder(fn () => true, fn () => null),
    ]);

    expect($resolver->resolve(makeMovieRequest()))->toBeNull();
});

it('drops oversize match (defense in depth)', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('50 GB', 1)),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 2)),
    ]);

    expect($resolver->resolve(makeMovieRequest(15_000_000_000))['torrent_id'])->toBe(2);
});

it('re-throws rate-limit exception', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => throw new IptorrentsRateLimitExceededException),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 1)),
    ]);

    expect(fn () => $resolver->resolve(makeMovieRequest()))
        ->toThrow(IptorrentsRateLimitExceededException::class);
});

it('re-throws auth exception', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => throw new IptorrentsAuthException),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 1)),
    ]);

    expect(fn () => $resolver->resolve(makeMovieRequest()))
        ->toThrow(IptorrentsAuthException::class);
});

it('catches generic Throwable and continues', function () {
    $resolver = new TorrentResolver([
        makeFinder(fn () => true, fn () => throw new RuntimeException('boom')),
        makeFinder(fn () => true, fn () => makeResolverTorrentResult('5 GB', 7)),
    ]);

    expect($resolver->resolve(makeMovieRequest())['torrent_id'])->toBe(7);
});
