@props([
    'status',
])

<flux:tooltip :content="$status->value">
    <x-dynamic-component :component="'flux::icon.' . $status->icon()" variant="micro" />
</flux:tooltip>
