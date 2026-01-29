<?php

use App\Models\Show;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Show $show;

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

    public function synopsisText(): ?string
    {
        if (! $this->show->summary) {
            return null;
        }

        return trim(strip_tags($this->show->summary));
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

        if ($this->show->rating && ($this->show->rating['average'] ?? null)) {
            $items[] = ['type' => 'rating', 'value' => (string) $this->show->rating['average']];
        }

        return $items;
    }

    public function backgroundUrl(): ?string
    {
        return $this->show->artUrl('showbackground');
    }

    public function clearartUrl(): ?string
    {
        return $this->show->artUrl('hdtvlogo');
    }

    /**
     * @return array{prefix: string, label: string}|null
     */
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
    <div class="relative h-[33vh] max-h-80 min-h-56 overflow-hidden bg-zinc-900">
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
                @if ($this->clearartUrl())
                    <img
                        src="{{ $this->clearartUrl() }}"
                        alt="{{ $show->name }} logo"
                        class="h-12 w-auto max-w-full drop-shadow sm:h-14 md:h-20"
                        onerror="
                            this.classList.add('hidden')
                            this.nextElementSibling.classList.remove('hidden')
                        "
                    />
                    <flux:heading size="xl" class="hidden" data-fallback>{{ $show->name }}</flux:heading>
                @else
                    <flux:heading size="xl">{{ $show->name }}</flux:heading>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-300">
                @foreach ($this->metadataItems() as $index => $item)
                    @if ($index > 0)
                        <span class="text-zinc-600">&middot;</span>
                    @endif

                    @if ($item['type'] === 'rating')
                        <span class="flex items-center gap-1 text-zinc-100">
                            <flux:icon name="star" variant="solid" class="size-4 text-yellow-500" />
                            <span>{{ $item['value'] }}/10</span>
                        </span>
                    @else
                        <span>{{ $item['value'] }}</span>
                    @endif
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
                        icon="arrow-top-right-on-square"
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
                        <flux:badge>{{ $genre }}</flux:badge>
                    @endforeach
                </div>
            @endif

            @if ($this->synopsisText())
                <div class="flex max-w-3xl flex-col gap-2">
                    <div class="prose prose-zinc dark:prose-invert max-w-none text-zinc-100">
                        <p class="line-clamp-2">{{ $this->synopsisText() }}</p>
                    </div>

                    <flux:modal.trigger name="show-summary">
                        <button
                            type="button"
                            class="w-fit text-sm font-semibold tracking-wide text-zinc-300 uppercase transition hover:text-white"
                        >
                            Show more
                        </button>
                    </flux:modal.trigger>
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

    @if ($this->synopsisText())
        <flux:modal name="show-summary" class="w-full max-w-2xl">
            <div class="space-y-4">
                <flux:heading size="lg">{{ $show->name }}</flux:heading>
                <div class="prose prose-zinc dark:prose-invert max-w-none">
                    {!! $show->summary !!}
                </div>
            </div>
        </flux:modal>
    @endif
</div>
