<?php

namespace App\Models;

use App\Enums\MediaType;
use App\Models\Concerns\HasArtwork;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

class Movie extends Model
{
    /** @use HasFactory<\Database\Factories\MovieFactory> */
    use HasArtwork, HasFactory, Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'runtime' => 'integer',
            'num_votes' => 'integer',
            'genres' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'imdb_id' => $this->imdb_id,
            'title' => $this->title,
            'year' => $this->year,
            'num_votes' => $this->num_votes,
        ];
    }

    public function getMediaType(): MediaType
    {
        return MediaType::MOVIE;
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    protected function artworkExternalIdValue(): string|int|null
    {
        return $this->imdb_id;
    }

    protected function artworkMediableType(): string
    {
        return 'movie';
    }
}
