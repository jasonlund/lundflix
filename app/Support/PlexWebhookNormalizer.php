<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class PlexWebhookNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     server_uuid: string,
     *     server_name: string|null,
     *     group_key: string,
     *     group_type: string,
     *     item_key: string,
     *     item: array<string, mixed>,
     *     warnings: list<string>
     * }
     */
    public function normalize(array $payload): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $payload['Metadata'] ?? [];
        /** @var array<string, mixed> $server */
        $server = $payload['Server'] ?? [];

        $mediaType = (string) ($metadata['type'] ?? 'unknown');
        $ratingKey = $this->stringOrNull($metadata['ratingKey'] ?? null);
        $parentRatingKey = $this->stringOrNull($metadata['parentRatingKey'] ?? null);
        $grandparentRatingKey = $this->stringOrNull($metadata['grandparentRatingKey'] ?? null);
        $key = $this->stringOrNull($metadata['key'] ?? null);
        $parentKey = $this->stringOrNull($metadata['parentKey'] ?? null);
        $grandparentKey = $this->stringOrNull($metadata['grandparentKey'] ?? null);
        $guid = $this->stringOrNull($metadata['guid'] ?? null);
        $receivedAt = now()->timestamp;

        $item = [
            'media_type' => $mediaType,
            'title' => $this->stringOrNull($metadata['title'] ?? null) ?? 'Unknown',
            'year' => $mediaType === 'movie' ? $this->integerOrNull($metadata['year'] ?? null) : null,
            'show_title' => $mediaType === 'episode'
                ? ($this->stringOrNull($metadata['grandparentTitle'] ?? null) ?? $this->stringOrNull($metadata['parentTitle'] ?? null))
                : null,
            'season' => $mediaType === 'episode' ? $this->integerOrNull($metadata['parentIndex'] ?? null) : null,
            'episode_number' => $mediaType === 'episode' ? $this->integerOrNull($metadata['index'] ?? null) : null,
            'rating_key' => $ratingKey,
            'parent_rating_key' => $parentRatingKey,
            'grandparent_rating_key' => $grandparentRatingKey,
            'key' => $key,
            'parent_key' => $parentKey,
            'grandparent_key' => $grandparentKey,
            'guid' => $guid,
            'library_section_id' => $this->integerOrNull($metadata['librarySectionID'] ?? null),
            'added_at' => $this->integerOrNull($metadata['addedAt'] ?? null),
            'received_at' => $receivedAt,
        ];

        $warnings = [];

        if ($ratingKey === null) {
            $warnings[] = 'missing_rating_key';
        }

        if ($mediaType === 'episode' && $grandparentRatingKey === null && $grandparentKey === null) {
            $warnings[] = 'missing_grandparent_key';
        }

        return [
            'server_uuid' => $this->stringOrNull($server['uuid'] ?? null) ?? 'unknown',
            'server_name' => $this->stringOrNull($server['title'] ?? null),
            'group_key' => $this->groupKey($item),
            'group_type' => $mediaType === 'episode' ? 'show' : 'movie',
            'item_key' => $this->itemKey($item),
            'item' => $item,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function groupKey(array $item): string
    {
        if ($item['media_type'] === 'episode') {
            if ($item['grandparent_rating_key']) {
                return "show:grandparent-rating-key:{$item['grandparent_rating_key']}";
            }

            if ($item['grandparent_key']) {
                return 'show:grandparent-key:'.$item['grandparent_key'];
            }

            return 'show:title:'.$this->slug($item['show_title'] ?? $item['title']);
        }

        if ($item['rating_key']) {
            return "movie:rating-key:{$item['rating_key']}";
        }

        if ($item['guid']) {
            return "movie:guid:{$item['guid']}";
        }

        return sprintf(
            'movie:title:%s:%s',
            $this->slug($item['title']),
            $item['year'] ?? 'unknown'
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemKey(array $item): string
    {
        if ($item['rating_key']) {
            return "rating-key:{$item['rating_key']}";
        }

        if ($item['key']) {
            return "key:{$item['key']}";
        }

        if ($item['guid']) {
            return "guid:{$item['guid']}";
        }

        if ($item['media_type'] === 'episode') {
            return sprintf(
                'episode:%s:%s',
                $item['season'] ?? 'unknown',
                $item['episode_number'] ?? 'unknown'
            );
        }

        return sprintf(
            'movie:%s:%s',
            $this->slug($item['title']),
            $item['year'] ?? 'unknown'
        );
    }

    private function slug(?string $value): string
    {
        return Str::slug($value ?? 'unknown') ?: 'unknown';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
