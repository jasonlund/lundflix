@props([
    'genre',
])

<flux:badge :icon="\App\Enums\Genre::iconFor($genre)">
    {{ \App\Enums\Genre::labelFor($genre) }}
</flux:badge>
