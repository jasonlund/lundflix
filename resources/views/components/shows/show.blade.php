<?php

use App\Models\Show;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Show $show;

    #[Computed]
    public function episodes(): Collection
    {
        return $this->show->episodes;
    }

    public function imdbUrl(): string
    {
        return "https://www.imdb.com/title/{$this->show->imdb_id}/";
    }

    public function yearRange(): ?string
    {
        if (! $this->show->premiered) {
            return null;
        }

        $startYear = $this->show->premiered->year;

        if ($this->show->ended) {
            return "{$startYear}–{$this->show->ended->year}";
        }

        if ($this->show->status === 'Running') {
            return "{$startYear}–";
        }

        return (string) $startYear;
    }

    public function runtimeText(): ?string
    {
        if (! $this->show->runtime) {
            return null;
        }

        return "{$this->show->runtime} min";
    }

    /**
     * @return array<int, array{type: string, value: string}>
     */
    public function metadataItems(): array
    {
        $items = [];

        if ($yearRange = $this->yearRange()) {
            $items[] = ['type' => 'text', 'value' => $yearRange];
        }

        if ($runtime = $this->runtimeText()) {
            $items[] = ['type' => 'text', 'value' => $runtime];
        }

        return $items;
    }

    #[Computed]
    public function backgroundUrl(): ?string
    {
        return $this->show->artUrl('background');
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->show->artUrl('logo');
    }

    /**
     * @return list<array{prefix: string, label: string, logoUrl: string|null}>
     */
    #[Computed]
    public function networkInfoItems(): array
    {
        $items = [];

        if ($this->show->network) {
            $label = $this->show->network['name'];
            if (isset($this->show->network['country']['name'])) {
                $label .= " ({$this->show->network['country']['name']})";
            }

            $items[] = ['prefix' => 'Network:', 'label' => $label, 'logoUrl' => $this->show->networkLogoUrl()];
        }

        if ($this->show->web_channel) {
            $items[] = [
                'prefix' => 'Streaming:',
                'label' => $this->show->web_channel['name'],
                'logoUrl' => $this->show->streamingLogoUrl(),
            ];
        }

        return $items;
    }

    #[Computed]
    public function scheduleLabel(): ?string
    {
        if (! $this->show->schedule || ! ($this->show->schedule['days'] ?? false)) {
            return null;
        }

        $label = implode(', ', $this->show->schedule['days']);
        if ($this->show->schedule['time']) {
            $label .= " at {$this->show->schedule['time']}";
        }

        return $label;
    }
};
?>

<div class="flex flex-col gap-8">
    <div class="relative aspect-video min-h-56 overflow-hidden bg-zinc-900">
        @if ($this->backgroundUrl())
            <img
                src="{{ $this->backgroundUrl() }}"
                alt="{{ $show->name }} background"
                class="absolute inset-0 h-full w-full object-cover"
            />
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-zinc-950/95 via-zinc-950/70 to-zinc-950/20"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-zinc-950/75 via-transparent to-transparent"></div>

        <div class="relative flex h-full flex-col gap-4 px-4 py-5 sm:px-6 sm:py-6">
            <div class="max-w-4xl">
                @if ($this->logoUrl())
                    <img
                        src="{{ $this->logoUrl() }}"
                        alt="{{ $show->name }} logo"
                        class="h-12 w-auto max-w-full drop-shadow sm:h-14 md:h-20"
                    />
                @else
                    <flux:heading size="xl">{{ $show->name }}</flux:heading>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-300">
                @foreach ($this->metadataItems() as $index => $item)
                    @if ($index > 0)
                        <span class="text-zinc-600">&middot;</span>
                    @endif

                    <span>{{ $item['value'] }}</span>
                @endforeach

                <flux:badge
                    :color="$show->status === 'Running' ? 'green' : ($show->status === 'Ended' ? 'red' : 'zinc')"
                >
                    {{ $show->status }}
                </flux:badge>

                @if ($show->imdb_id)
                    <flux:button
                        as="a"
                        href="{{ $this->imdbUrl() }}"
                        target="_blank"
                        icon="external-link"
                        size="sm"
                        variant="ghost"
                    >
                        IMDB
                    </flux:button>
                @endif
            </div>

            @if ($show->genres && count($show->genres))
                <div class="flex flex-wrap gap-2">
                    @foreach ($show->genres as $genre)
                        <x-genre-badge :$genre />
                    @endforeach
                </div>
            @endif

            @if ($this->networkInfoItems() || $this->scheduleLabel())
                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                    @foreach ($this->networkInfoItems() as $info)
                        <span class="flex items-center gap-2">
                            @if ($info['logoUrl'])
                                <img
                                    src="{{ $info['logoUrl'] }}"
                                    alt="{{ $info['label'] }}"
                                    class="h-5 w-auto object-contain"
                                />
                            @endif

                            <span>
                                <span class="font-medium text-zinc-200">{{ $info['prefix'] }}</span>
                                {{ $info['label'] }}
                            </span>
                        </span>
                    @endforeach

                    @if ($this->networkInfoItems() && $this->scheduleLabel())
                        <span class="text-zinc-600">&middot;</span>
                    @endif

                    @if ($this->scheduleLabel())
                        <span>
                            <span class="font-medium text-zinc-200">Schedule:</span>
                            {{ $this->scheduleLabel() }}
                        </span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-8 px-4 sm:px-6">
        @if ($show->imdb_id)
            <livewire:shows.plex-availability :show="$show" lazy />
        @endif

        @if ($this->episodes()->isNotEmpty())
            <livewire:shows.episodes :show="$show" :episodes="$this->episodes()" />
        @else
            <livewire:shows.episodes :show="$show" lazy />
        @endif
    </div>
</div>
