<x-filament-widgets::widget class="fi-wi-table">
    @if ($this->hasSearchableItems())
        {{ $this->table }}
    @endif
</x-filament-widgets::widget>
