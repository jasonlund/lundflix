<?php

namespace App\Casts;

use App\Enums\Language;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast a language column that stores full English names (e.g., "English")
 * to and from the Language enum (which uses ISO 639-1 backing values).
 *
 * @implements CastsAttributes<Language|null, string|null>
 */
class LanguageFromName implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Language
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Language::tryFromName($value);
    }

    /**
     * @param  Language|string|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Language) {
            return $value->getLabel();
        }

        return $value;
    }
}
