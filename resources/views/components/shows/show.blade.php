<?php

use App\Models\Show;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Show $show;
};
?>

<div>
    <div class="flex flex-col gap-8 md:flex-row">
        @if ($show->image)
            <div class="shrink-0">
                <img
                    src="{{ $show->image['original'] ?? $show->image['medium'] }}"
                    alt="{{ $show->name }}"
                    class="w-full rounded-lg shadow-lg md:w-64"
                />
            </div>
        @endif

        <div class="flex-1 space-y-4">
            <div>
                <flux:heading size="xl">{{ $show->name }}</flux:heading>
                <flux:text class="mt-1">
                    @if ($show->premiered)
                        {{ $show->premiered->year }}@if ($show->ended)–{{ $show->ended->year }}@elseif ($show->status === 'Running')–@endif
                    @endif
                    @if ($show->type)
                        · {{ $show->type }}
                    @endif
                    @if ($show->runtime)
                        · {{ $show->runtime }} min
                    @endif
                </flux:text>
            </div>

            @if ($show->rating && $show->rating['average'])
                <div class="flex items-center gap-2">
                    <flux:icon name="star" variant="solid" class="size-5 text-yellow-500" />
                    <flux:text class="font-medium">{{ $show->rating['average'] }}/10</flux:text>
                </div>
            @endif

            @if ($show->genres && count($show->genres))
                <div class="flex flex-wrap gap-2">
                    @foreach ($show->genres as $genre)
                        <flux:badge>{{ $genre }}</flux:badge>
                    @endforeach
                </div>
            @endif

            @if ($show->network)
                <flux:text>
                    <span class="font-medium">Network:</span>
                    {{ $show->network['name'] }}
                    @if (isset($show->network['country']['name']))
                        ({{ $show->network['country']['name'] }})
                    @endif
                </flux:text>
            @elseif ($show->web_channel)
                <flux:text>
                    <span class="font-medium">Streaming:</span>
                    {{ $show->web_channel['name'] }}
                </flux:text>
            @endif

            @if ($show->schedule && ($show->schedule['days'] ?? false))
                <flux:text>
                    <span class="font-medium">Schedule:</span>
                    {{ implode(', ', $show->schedule['days']) }}
                    @if ($show->schedule['time'])
                        at {{ $show->schedule['time'] }}
                    @endif
                </flux:text>
            @endif

            <flux:badge :color="$show->status === 'Running' ? 'green' : ($show->status === 'Ended' ? 'red' : 'zinc')">
                {{ $show->status }}
            </flux:badge>

            @if ($show->summary)
                <div class="prose prose-zinc dark:prose-invert max-w-none">
                    {!! $show->summary !!}
                </div>
            @endif

            @if ($show->official_site)
                <flux:button as="a" href="{{ $show->official_site }}" target="_blank" icon="arrow-top-right-on-square">
                    Official Site
                </flux:button>
            @endif
        </div>
    </div>
</div>
