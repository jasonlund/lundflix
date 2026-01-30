<?php

namespace App\Models;

use App\Enums\MovieArtwork;
use App\Enums\TvArtwork;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'likes' => 'integer',
            'season' => 'integer',
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
     * Get the artwork enum for this media item.
     */
    public function getArtwork(): TvArtwork|MovieArtwork|null
    {
        return TvArtwork::tryFrom($this->type)
            ?? MovieArtwork::tryFrom($this->type);
    }

    /**
     * Get the display label for this media's artwork type.
     */
    public function getTypeLabel(): string
    {
        return $this->getArtwork()?->getLabel() ?? $this->type;
    }
}
