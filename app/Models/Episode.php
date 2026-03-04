<?php

namespace App\Models;

use App\Enums\EpisodeType;
use App\Enums\MediaType;
use App\Support\EpisodeCode;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property EpisodeType $type
 */
class Episode extends Model
{
    /** @use HasFactory<\Database\Factories\EpisodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => EpisodeType::class,
            'season' => 'integer',
            'number' => 'integer',
            'runtime' => 'integer',
            'rating' => 'array',
            'image' => 'array',
            'airdate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Show, $this>
     */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function getMediaType(): MediaType
    {
        return MediaType::EPISODE;
    }

    /**
     * Get the episode code (e.g., s01e05 for regular, s01s01 for special).
     *
     * @return Attribute<string, never>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (): string => EpisodeCode::generate(
                $this->season,
                $this->number,
                $this->isSpecial()
            ),
        )->shouldCache();
    }

    /**
     * Check if this episode is a significant special.
     */
    public function isSpecial(): bool
    {
        return $this->type === EpisodeType::SignificantSpecial;
    }

    /**
     * Get the display code for an episode (uppercase).
     * Supports both Episode models and API arrays.
     *
     * @param  self|array<string, mixed>  $episode
     */
    public static function displayCode(self|array $episode): string
    {
        if ($episode instanceof self) {
            return strtoupper($episode->code);
        }

        // API array data
        $isSpecial = ($episode['type'] ?? 'regular') === EpisodeType::SignificantSpecial->value;

        return strtoupper(EpisodeCode::generate(
            $episode['season'],
            $episode['number'] ?? 1,
            $isSpecial
        ));
    }
}
