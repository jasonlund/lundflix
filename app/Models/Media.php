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

    /**
     * Activate this media item, deactivating any siblings with the same type and season.
     * Downloads and stores the image if not already stored.
     */
    public function activate(): void
    {
        // Deactivate siblings with same type and season
        static::query()
            ->where('mediable_type', $this->mediable_type)
            ->where('mediable_id', $this->mediable_id)
            ->where('type', $this->type)
            ->where('season', $this->season)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true, 'path' => $this->path]);
    }

    /**
     * Deactivate this media item.
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
