<?php

namespace App\Actions\Media;

use App\Models\Media;

class ActivateMedia
{
    /**
     * Activate a media item, deactivating any siblings with the same type and season.
     */
    public function activate(Media $media): void
    {
        // Deactivate siblings with same type and season
        Media::query()
            ->where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id)
            ->where('type', $media->type)
            ->where('season', $media->season)
            ->where('id', '!=', $media->id)
            ->update(['is_active' => false]);

        $media->update(['is_active' => true, 'path' => $media->path]);
    }

    /**
     * Deactivate a media item.
     */
    public function deactivate(Media $media): void
    {
        $media->update(['is_active' => false]);
    }
}
