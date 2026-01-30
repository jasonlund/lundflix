<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum Genre: string
{
    case Action = 'action';
    case Adult = 'adult';
    case Adventure = 'adventure';
    case Animation = 'animation';
    case Anime = 'anime';
    case Biography = 'biography';
    case Children = 'children';
    case Comedy = 'comedy';
    case Crime = 'crime';
    case DIY = 'diy';
    case Documentary = 'documentary';
    case Drama = 'drama';
    case Espionage = 'espionage';
    case Family = 'family';
    case Fantasy = 'fantasy';
    case FilmNoir = 'film-noir';
    case Food = 'food';
    case GameShow = 'game-show';
    case History = 'history';
    case Horror = 'horror';
    case Legal = 'legal';
    case Medical = 'medical';
    case Music = 'music';
    case Musical = 'musical';
    case Mystery = 'mystery';
    case Nature = 'nature';
    case News = 'news';
    case RealityTV = 'reality-tv';
    case Romance = 'romance';
    case SciFi = 'sci-fi';
    case Sport = 'sport';
    case Supernatural = 'supernatural';
    case TalkShow = 'talk-show';
    case Thriller = 'thriller';
    case Travel = 'travel';
    case War = 'war';
    case Western = 'western';

    private const ALIASES = [
        'science-fiction' => 'sci-fi',
        'sports' => 'sport',
    ];

    public function label(): string
    {
        return match ($this) {
            self::DIY => 'DIY',
            self::FilmNoir => 'Film Noir',
            self::GameShow => 'Game Show',
            self::RealityTV => 'Reality TV',
            self::SciFi => 'Sci-Fi',
            self::TalkShow => 'Talk Show',
            default => Str::title($this->value),
        };
    }

    public static function tryFromString(string $value): ?self
    {
        $normalized = Str::lower($value);

        if (isset(self::ALIASES[$normalized])) {
            $normalized = self::ALIASES[$normalized];
        }

        return self::tryFrom($normalized);
    }

    public static function labelFor(string $value): string
    {
        $genre = self::tryFromString($value);

        if ($genre !== null) {
            return $genre->label();
        }

        return Str::of($value)
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }
}
