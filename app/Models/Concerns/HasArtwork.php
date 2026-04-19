<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ArtworkType;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Casts\Attribute;

/** @property string|int|null $artwork_external_id */
trait HasArtwork
{
    private const ART_TYPE_MAP = [
        'logo' => ArtworkType::Logo,
        'poster' => ArtworkType::Poster,
        'background' => ArtworkType::Backdrop,
    ];

    public function artUrl(string $type, ?string $size = null): ?string
    {
        if (! $this->hasActiveMedia($type)) {
            return null;
        }

        $url = route('art', [
            'mediable' => $this->artworkMediableType(),
            'id' => Sqid::encode($this->id),
            'type' => $type,
        ]);

        if ($size !== null) {
            $url .= '?size='.$size;
        }

        return $url;
    }

    public function hasActiveMedia(string $type): bool
    {
        if (! $this->canHaveArt()) {
            return false;
        }

        $artworkType = self::ART_TYPE_MAP[$type] ?? null;

        if ($artworkType === null) {
            return false;
        }

        if ($this->relationLoaded('media')) {
            return $this->media->contains(
                fn ($m): bool => $m->type === $artworkType && $m->is_active,
            );
        }

        return $this->media()
            ->where('type', $artworkType->value)
            ->where('is_active', true)
            ->exists();
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
