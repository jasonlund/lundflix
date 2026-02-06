<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

/** @property string|int|null $artwork_external_id */
trait HasArtwork
{
    public function artUrl(string $type, bool $preview = false): ?string
    {
        if (! $this->canFetchArt($type)) {
            return null;
        }

        $params = ['mediable' => $this->artworkMediableType(), 'id' => $this->id, 'type' => $type];

        if ($preview) {
            $params['preview'] = 1;
        }

        return route('art', $params);
    }

    public function canHaveArt(): bool
    {
        return $this->artwork_external_id !== null;
    }

    public function canFetchArt(string $type): bool
    {
        if (! $this->canHaveArt()) {
            return false;
        }

        return ! Cache::has($this->artMissingCacheKey())
            && ! Cache::has($this->artMissingTypeCacheKey($type));
    }

    public function artMissingCacheKey(): string
    {
        return "fanart:missing:{$this->artworkMediableType()}:{$this->id}";
    }

    public function artMissingTypeCacheKey(string $type): string
    {
        return "{$this->artMissingCacheKey()}:{$type}";
    }

    protected function artworkExternalId(): Attribute
    {
        return Attribute::get(fn (): string|int|null => $this->artworkExternalIdValue())->shouldCache();
    }

    abstract protected function artworkExternalIdValue(): string|int|null;

    abstract protected function artworkMediableType(): string;
}
