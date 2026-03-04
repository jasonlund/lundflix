<?php

use App\Enums\EpisodeType;
use App\Enums\Genre;
use App\Enums\IptCategory;
use App\Enums\Language;
use App\Enums\MediaType;
use App\Enums\MovieArtwork;
use App\Enums\MovieArtworkLevel;
use App\Enums\MovieStatus;
use App\Enums\NetworkLogo;
use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Enums\ShowStatus;
use App\Enums\StreamingLogo;
use App\Enums\TvArtwork;
use App\Enums\TvArtworkLevel;
use App\Enums\UserRole;

it('snapshots EpisodeType display values', function () {
    $values = collect(EpisodeType::cases())->mapWithKeys(fn (EpisodeType $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots Genre display values', function () {
    $values = collect(Genre::cases())->mapWithKeys(fn (Genre $e) => [
        $e->name => ['label' => $e->getLabel(), 'icon' => $e->getIcon()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots IptCategory display values', function () {
    $values = collect(IptCategory::cases())->mapWithKeys(fn (IptCategory $e) => [
        $e->name => ['label' => $e->getLabel()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots Language display values', function () {
    $values = collect(Language::cases())->mapWithKeys(fn (Language $e) => [
        $e->name => ['label' => $e->getLabel()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots MediaType display values', function () {
    $values = collect(MediaType::cases())->mapWithKeys(fn (MediaType $e) => [
        $e->name => ['label' => $e->getLabel()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots MovieArtwork display values', function () {
    $values = collect(MovieArtwork::cases())->mapWithKeys(fn (MovieArtwork $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots MovieArtworkLevel display values', function () {
    $values = collect(MovieArtworkLevel::cases())->mapWithKeys(fn (MovieArtworkLevel $e) => [
        $e->name => ['label' => $e->getLabel()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots MovieStatus display values', function () {
    $values = collect(MovieStatus::cases())->mapWithKeys(fn (MovieStatus $e) => [
        $e->name => [
            'label' => $e->getLabel(),
            'color' => $e->getColor(),
            'icon' => $e->getIcon(),
            'iconColorClass' => $e->iconColorClass(),
        ],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots NetworkLogo display values', function () {
    $values = collect(NetworkLogo::cases())->mapWithKeys(fn (NetworkLogo $e) => [
        $e->name => ['file' => $e->file(), 'source' => $e->source()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots RequestItemStatus display values', function () {
    $values = collect(RequestItemStatus::cases())->mapWithKeys(fn (RequestItemStatus $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots RequestStatus display values', function () {
    $values = collect(RequestStatus::cases())->mapWithKeys(fn (RequestStatus $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots ShowStatus display values', function () {
    $values = collect(ShowStatus::cases())->mapWithKeys(fn (ShowStatus $e) => [
        $e->name => [
            'label' => $e->getLabel(),
            'color' => $e->getColor(),
            'icon' => $e->getIcon(),
            'iconColorClass' => $e->iconColorClass(),
        ],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots StreamingLogo display values', function () {
    $values = collect(StreamingLogo::cases())->mapWithKeys(fn (StreamingLogo $e) => [
        $e->name => ['file' => $e->file(), 'source' => $e->source()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots TvArtwork display values', function () {
    $values = collect(TvArtwork::cases())->mapWithKeys(fn (TvArtwork $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots TvArtworkLevel display values', function () {
    $values = collect(TvArtworkLevel::cases())->mapWithKeys(fn (TvArtworkLevel $e) => [
        $e->name => ['label' => $e->getLabel()],
    ])->all();

    expect($values)->toMatchSnapshot();
});

it('snapshots UserRole display values', function () {
    $values = collect(UserRole::cases())->mapWithKeys(fn (UserRole $e) => [
        $e->name => ['label' => $e->getLabel(), 'color' => $e->getColor(), 'icon' => $e->getIcon()],
    ])->all();

    expect($values)->toMatchSnapshot();
});
