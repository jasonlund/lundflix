<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArtworkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property ArtworkType $type
 */
class Media extends Model
{
    use HasFactory;

    private const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p';

    protected function casts(): array
    {
        return [
            'type' => ArtworkType::class,
            'vote_average' => 'float',
            'vote_count' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'season' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Construct the full TMDB CDN URL for a given size.
     */
    public function url(?string $size = null): string
    {
        $size ??= $this->type->defaultSize();

        return self::IMAGE_BASE_URL.'/'.$size.$this->file_path;
    }

    /**
     * Get the full-resolution original URL.
     */
    public function originalUrl(): string
    {
        return $this->url('original');
    }
}
