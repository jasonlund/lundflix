<?php

use App\Enums\MovieArtwork;
use App\Enums\MovieArtworkLevel;

it('has correct api keys for all cases', function () {
    expect(MovieArtwork::HdClearLogo->value)->toBe('hdmovielogo')
        ->and(MovieArtwork::Poster->value)->toBe('movieposter')
        ->and(MovieArtwork::HdClearArt->value)->toBe('hdmovieclearart')
        ->and(MovieArtwork::CdArt->value)->toBe('moviedisc')
        ->and(MovieArtwork::Background->value)->toBe('moviebackground')
        ->and(MovieArtwork::Background4k->value)->toBe('moviebackground-4k')
        ->and(MovieArtwork::Banner->value)->toBe('moviebanner')
        ->and(MovieArtwork::MovieThumbs->value)->toBe('moviethumb');
});

it('returns movie level for all artwork types', function (MovieArtwork $artwork) {
    expect($artwork->level())->toBe(MovieArtworkLevel::Movie);
})->with([
    'hd clear logo' => MovieArtwork::HdClearLogo,
    'poster' => MovieArtwork::Poster,
    'hd clear art' => MovieArtwork::HdClearArt,
    'cd art' => MovieArtwork::CdArt,
    'background' => MovieArtwork::Background,
    'background 4k' => MovieArtwork::Background4k,
    'banner' => MovieArtwork::Banner,
    'movie thumbs' => MovieArtwork::MovieThumbs,
]);

it('returns correct labels', function () {
    expect(MovieArtwork::HdClearLogo->getLabel())->toBe('HD ClearLOGO')
        ->and(MovieArtwork::HdClearArt->getLabel())->toBe('HD ClearART')
        ->and(MovieArtwork::CdArt->getLabel())->toBe('cdART')
        ->and(MovieArtwork::Background4k->getLabel())->toBe('4K Background')
        ->and(MovieArtwork::Poster->getLabel())->toBe('Poster')
        ->and(MovieArtwork::Background->getLabel())->toBe('Background')
        ->and(MovieArtwork::Banner->getLabel())->toBe('Banner')
        ->and(MovieArtwork::MovieThumbs->getLabel())->toBe('Movie Thumbs');
});

it('has correct label for movie artwork level', function () {
    expect(MovieArtworkLevel::Movie->getLabel())->toBe('Movie');
});
