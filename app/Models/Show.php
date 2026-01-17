<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;

class Show extends Model
{
    /** @use HasFactory<\Database\Factories\ShowFactory> */
    use HasFactory, Searchable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'schedule' => 'array',
            'rating' => 'array',
            'network' => 'array',
            'web_channel' => 'array',
            'externals' => 'array',
            'image' => 'array',
            'premiered' => 'date',
            'ended' => 'date',
            'num_votes' => 'integer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['name'])]
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'imdb_id' => $this->imdb_id,
            'name' => $this->name,
            'year' => $this->premiered?->year, // @phpstan-ignore property.nonObject (casted to Carbon)
            'num_votes' => $this->num_votes,
        ];
    }

    /**
     * @return HasMany<Episode, $this>
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }
}
