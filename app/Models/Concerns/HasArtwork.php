<?php

namespace App\Models\Concerns;

use App\Support\Sqid;
use Illuminate\Database\Eloquent\Casts\Attribute;

/** @property string|int|null $artwork_external_id */
trait HasArtwork
{
    public function artUrl(string $type): ?string
    {
        if (! $this->canHaveArt()) {
            return null;
        }

        return route('art', [
            'mediable' => $this->artworkMediableType(),
            'id' => Sqid::encode($this->id),
            'type' => $type,
        ]);
    }

    public function canHaveArt(): bool
    {
        return $this->artwork_external_id !== null;
    }

    protected function artworkExternalId(): Attribute
    {
        return Attribute::get(fn (): string|int|null => $this->artworkExternalIdValue())->shouldCache();
    }

    abstract protected function artworkExternalIdValue(): string|int|null;

    abstract protected function artworkMediableType(): string;
}
