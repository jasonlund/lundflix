@props([
    'genre',
    'size' => null,
])

<flux:badge :icon="\App\Enums\Genre::iconFor($genre)" :size="$size">
    {{ \App\Enums\Genre::labelFor($genre) }}
</flux:badge>
