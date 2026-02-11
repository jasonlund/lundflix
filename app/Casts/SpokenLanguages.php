<?php

namespace App\Casts;

use App\Enums\Language;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast the spoken_languages JSON column from TMDB-style objects
 * to an array of Language enums.
 *
 * DB format: [{"iso_639_1": "en", "english_name": "English", "name": "English"}, ...]
 * App format: [Language::English, Language::French, ...]
 *
 * @implements CastsAttributes<array<int, Language>, array<int, Language|array<string, mixed>>|string|null>
 */
class SpokenLanguages implements CastsAttributes
{
    /**
     * @return array<int, Language>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        /** @var array<int, array{iso_639_1: string, english_name?: string, name?: string}> $decoded */
        $decoded = json_decode($value, true) ?? [];

        return collect($decoded)
            ->map(fn (array $entry): ?Language => Language::tryFrom($entry['iso_639_1']))
            ->filter()
            ->values()
            ->all();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (isset($value[0]) && $value[0] instanceof Language) {
            return json_encode(array_map(fn (Language $lang): array => [
                'iso_639_1' => $lang->value,
                'english_name' => $lang->getLabel(),
                'name' => $lang->getLabel(),
            ], array_values($value)));
        }

        return json_encode($value);
    }
}
