<?php

use App\Enums\TvArtwork;
use App\Enums\TvArtworkLevel;

it('has correct api keys for all cases', function () {
    expect(TvArtwork::HdClearLogo->value)->toBe('hdtvlogo')
        ->and(TvArtwork::Poster->value)->toBe('tvposter')
        ->and(TvArtwork::SeasonPoster->value)->toBe('seasonposter')
        ->and(TvArtwork::HdClearArt->value)->toBe('hdclearart')
        ->and(TvArtwork::CharacterArt->value)->toBe('characterart')
        ->and(TvArtwork::TvThumbs->value)->toBe('tvthumb')
        ->and(TvArtwork::SeasonThumbs->value)->toBe('seasonthumb')
        ->and(TvArtwork::Background->value)->toBe('showbackground')
        ->and(TvArtwork::Banner->value)->toBe('tvbanner')
        ->and(TvArtwork::Background4k->value)->toBe('showbackground-4k')
        ->and(TvArtwork::SeasonBanner->value)->toBe('seasonbanner');
});

it('returns season level for season artwork types', function (TvArtwork $artwork) {
    expect($artwork->level())->toBe(TvArtworkLevel::Season);
})->with([
    'season poster' => TvArtwork::SeasonPoster,
    'season thumbs' => TvArtwork::SeasonThumbs,
    'season banner' => TvArtwork::SeasonBanner,
]);

it('returns show level for show artwork types', function (TvArtwork $artwork) {
    expect($artwork->level())->toBe(TvArtworkLevel::Show);
})->with([
    'hd clear logo' => TvArtwork::HdClearLogo,
    'poster' => TvArtwork::Poster,
    'hd clear art' => TvArtwork::HdClearArt,
    'character art' => TvArtwork::CharacterArt,
    'tv thumbs' => TvArtwork::TvThumbs,
    'background' => TvArtwork::Background,
    'banner' => TvArtwork::Banner,
    'background 4k' => TvArtwork::Background4k,
]);

it('returns correct labels', function () {
    expect(TvArtwork::HdClearLogo->getLabel())->toBe('HD ClearLOGO')
        ->and(TvArtwork::HdClearArt->getLabel())->toBe('HD ClearART')
        ->and(TvArtwork::CharacterArt->getLabel())->toBe('CharacterART')
        ->and(TvArtwork::TvThumbs->getLabel())->toBe('TV Thumbs')
        ->and(TvArtwork::SeasonThumbs->getLabel())->toBe('Season Thumbs')
        ->and(TvArtwork::Background4k->getLabel())->toBe('4K Background')
        ->and(TvArtwork::Poster->getLabel())->toBe('Poster')
        ->and(TvArtwork::SeasonPoster->getLabel())->toBe('Season Poster')
        ->and(TvArtwork::Background->getLabel())->toBe('Background')
        ->and(TvArtwork::Banner->getLabel())->toBe('Banner')
        ->and(TvArtwork::SeasonBanner->getLabel())->toBe('Season Banner');
});

it('has correct labels for tv artwork levels', function () {
    expect(TvArtworkLevel::Show->getLabel())->toBe('Show')
        ->and(TvArtworkLevel::Season->getLabel())->toBe('Season');
});
