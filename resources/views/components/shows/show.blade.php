<?php

use App\Models\Show;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
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
     * @return array{prefix: string, label: string}|null
     */
    #[Computed]
    public function networkInfo(): ?array
    {
        if ($this->show->network) {
            $label = $this->show->network['name'];
            if (isset($this->show->network['country']['name'])) {
                $label .= " ({$this->show->network['country']['name']})";
            }

            return ['prefix' => 'Network:', 'label' => $label];
        }

        if ($this->show->web_channel) {
            return ['prefix' => 'Streaming:', 'label' => $this->show->web_channel['name']];
        }

        return null;
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

    public function render(): mixed
    {
        return $this->view()->layout('components.layouts.app', [
            'backgroundImage' => $this->backgroundUrl(),
        ]);
    }
};
?>

<div class="flex flex-col">
    <div class="relative h-[16rem] overflow-hidden">
        <div class="relative flex h-full flex-col gap-4 px-4 py-5">
            <div>
                @if ($this->logoUrl())
                    <img
                        src="{{ $this->logoUrl() }}"
                        alt="{{ $show->name }} logo"
                        class="h-20 w-auto max-w-full drop-shadow"
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

            @if ($this->networkInfo() || $this->scheduleLabel())
                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-400">
                    @if ($this->networkInfo())
                        <span>
                            <span class="font-medium text-zinc-200">{{ $this->networkInfo()['prefix'] }}</span>
                            {{ $this->networkInfo()['label'] }}
                        </span>
                    @endif

                    @if ($this->networkInfo() && $this->scheduleLabel())
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
