<?php

namespace App\Casts;

use App\Enums\Language;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Cast a language column that stores ISO 639-1 codes (e.g., "en")
 * to and from the Language enum, gracefully handling unknown codes.
 *
 * @implements CastsAttributes<Language|null, string|null>
 */
class LanguageFromCode implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Language
    {
        if ($value === null || $value === '') {
            return null;
        }

        $language = Language::tryFrom($value);

        if ($language === null) {
            Log::warning('Unknown ISO 639-1 language code encountered', [
                'code' => $value,
                'model' => $model::class,
                'id' => $model->getKey(),
            ]);
        }

        return $language;
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
            return $value->value;
        }

        return $value;
    }
}
