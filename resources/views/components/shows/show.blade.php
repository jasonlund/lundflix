<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use App\Models\Subscription;
use App\Services\CartService;
use App\Support\Formatters;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public int $cartEpisodeCount = 0;

    public int $totalEpisodeCount = 0;

    public bool $isSubscribed = false;

    public function mount(CartService $cart): void
    {
        $this->cartEpisodeCount = $cart->countEpisodesForShow($this->show->id);
        $this->totalEpisodeCount = $this->show->episodes->count();
        $this->isSubscribed =
            auth()->check() &&
            Subscription::query()
                ->where('user_id', auth()->id())
                ->where('subscribable_type', Show::class)
                ->where('subscribable_id', $this->show->id)
                ->exists();
    }

    #[On('cart-updated')]
    public function refreshCartCount(CartService $cart): void
    {
        $this->cartEpisodeCount = $cart->countEpisodesForShow($this->show->id);
    }

    #[Computed]
    public function isSubscribable(): bool
    {
        $status = $this->show->status;

        if ($status === null) {
            return false;
        }

        return $status->isSubscribable();
    }

    public function toggleSubscription(): void
    {
        if (! $this->isSubscribable) {
            return;
        }

        $userId = auth()->id();

        if ($this->isSubscribed) {
            Subscription::query()
                ->where('user_id', $userId)
                ->where('subscribable_type', Show::class)
                ->where('subscribable_id', $this->show->id)
                ->delete();
            $this->isSubscribed = false;
        } else {
            Subscription::create([
                'user_id' => $userId,
                'subscribable_type' => Show::class,
                'subscribable_id' => $this->show->id,
            ]);
            $this->isSubscribed = true;
        }

        Flux::toast(
            text: __($this->isSubscribed ? 'lundbergh.toast.subscribed' : 'lundbergh.toast.unsubscribed', [
                'title' => $this->show->name,
            ]),
        );
    }

    #[Computed]
    public function episodes(): Collection
    {
        return $this->show->episodes;
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

    #[Computed]
    public function yearLabel(): ?string
    {
        return Formatters::yearLabel($this->show);
    }

    #[Computed]
    public function runtime(): ?string
    {
        return Formatters::runtimeFor($this->show);
    }

    #[Computed]
    public function isScheduleVisible(): bool
    {
        return $this->show->status !== ShowStatus::Ended && $this->scheduleLabel() !== null;
    }

    /**
     * @return list<array{name: string, tooltip: string, logoUrl: string|null}>
     */
    #[Computed]
    public function networkInfoItems(): array
    {
        $items = [];

        if ($this->show->network) {
            $name = $this->show->network['name'];
            $tooltip = $name;
            if (isset($this->show->network['country']['name'])) {
                $tooltip .= ' (' . $this->abbreviateCountry($this->show->network['country']['name']) . ')';
            }

            $items[] = ['name' => $name, 'tooltip' => $tooltip, 'logoUrl' => $this->show->networkLogoUrl()];
        }

        if ($this->show->web_channel) {
            $name = $this->show->web_channel['name'];
            $items[] = ['name' => $name, 'tooltip' => $name, 'logoUrl' => $this->show->streamingLogoUrl()];
        }

        return $items;
    }

    private function abbreviateCountry(string $country): string
    {
        return match ($country) {
            'United States' => 'US',
            'United Kingdom' => 'UK',
            'Australia' => 'AU',
            default => $country,
        };
    }

    #[Computed]
    public function scheduleLabel(): ?string
    {
        if (! $this->show->schedule || ! ($this->show->schedule['days'] ?? false)) {
            return null;
        }

        $days = $this->show->schedule['days'];

        usort($days, fn ($a, $b) => Carbon::parse($a)->dayOfWeekIso - Carbon::parse($b)->dayOfWeekIso);

        $abbrevs = array_map(fn ($d) => Carbon::parse($d)->minDayName, $days);

        $dayLabel = $this->collapseDayRanges($abbrevs);

        $time = $this->show->schedule['time'] ?? '';
        $timeLabel = $time !== '' ? $this->formatCompactTime($time) : null;

        return $timeLabel ? "{$dayLabel} {$timeLabel}" : $dayLabel;
    }

    /**
     * Collapse consecutive abbreviated days into en-dash ranges.
     *
     * @param  list<string>  $abbrevs
     */
    private function collapseDayRanges(array $abbrevs): string
    {
        $isoMap = [];
        for ($iso = 1; $iso <= 7; $iso++) {
            $isoMap[
                Carbon::now()
                    ->startOfWeek()
                    ->addDays($iso - 1)->minDayName
            ] = $iso;
        }

        $ranges = [];
        $currentRange = [$abbrevs[0]];

        for ($i = 1; $i < count($abbrevs); $i++) {
            if (($isoMap[$abbrevs[$i]] ?? 0) === ($isoMap[$abbrevs[$i - 1]] ?? 0) + 1) {
                $currentRange[] = $abbrevs[$i];
            } else {
                $ranges[] = $currentRange;
                $currentRange = [$abbrevs[$i]];
            }
        }
        $ranges[] = $currentRange;

        $parts = array_map(function (array $range) {
            if (count($range) >= 3) {
                return $range[0] . '–' . end($range);
            }

            return implode(', ', $range);
        }, $ranges);

        return implode(', ', $parts);
    }

    /**
     * Convert 24hr "HH:MM" to compact 12hr with 1-letter suffix.
     */
    private function formatCompactTime(string $time): string
    {
        $carbon = Carbon::createFromTimeString($time);
        $suffix = $carbon->format('a')[0];

        if ((int) $carbon->format('i') === 0) {
            return $carbon->format('g') . $suffix;
        }

        return $carbon->format('g:i') . $suffix;
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
    <div class="relative overflow-hidden">
        <div class="absolute top-4 right-4 z-10 flex items-center gap-2">
            @if ($this->isSubscribable)
                <div x-data="{ subscribed: {{ Js::from($isSubscribed) }}, syncing: false }">
                    <flux:tooltip
                        :content="$isSubscribed ? __('lundbergh.tooltip.unsubscribe') : __('lundbergh.tooltip.subscribe')"
                    >
                        <button
                            x-on:click="
                                subscribed = ! subscribed
                                syncing = true
                                $wire.toggleSubscription().then(() => {
                                    syncing = false
                                })
                            "
                            class="flex cursor-pointer items-center rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                        >
                            <div class="relative flex min-w-4 items-center justify-center">
                                <span class="invisible">+</span>
                                <span x-show="subscribed" x-cloak :class="syncing && 'opacity-0'" class="absolute">
                                    -
                                </span>
                                <span x-show="!subscribed" :class="syncing && 'opacity-0'" class="absolute">+</span>
                                <flux:icon.loading x-show="syncing" x-cloak class="absolute size-4" />
                            </div>
                        </button>
                    </flux:tooltip>
                </div>
            @elseif ($show->status !== null)
                <flux:tooltip :content="__('lundbergh.tooltip.subscribe_disabled')">
                    <div
                        class="flex items-center rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white/50 backdrop-blur-sm"
                    >
                        <div class="relative flex min-w-4 items-center justify-center">
                            <span>+</span>
                        </div>
                    </div>
                </flux:tooltip>
            @endif

            <div
                x-data="{ syncing: false }"
                @cart-syncing.window="syncing = true"
                @cart-updated.window="syncing = false"
            >
                <flux:tooltip content="Add/Remove Episodes Below">
                    <div
                        class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm"
                    >
                        <div class="relative flex min-w-4 items-center justify-center">
                            @if ($totalEpisodeCount > 0 && $cartEpisodeCount >= $totalEpisodeCount)
                                <span class="invisible">{{ $cartEpisodeCount }}</span>
                                <span :class="syncing && 'opacity-0'" class="absolute">
                                    <flux:icon.check class="size-4" />
                                </span>
                            @else
                                <span :class="syncing && 'opacity-0'">
                                    {{ $cartEpisodeCount > 0 ? $cartEpisodeCount : '-' }}
                                </span>
                            @endif
                            <flux:icon.loading x-show="syncing" x-cloak class="absolute size-4" />
                        </div>
                        <flux:icon.shopping-cart class="size-4" />
                    </div>
                </flux:tooltip>
            </div>
        </div>

        <div class="relative flex flex-col gap-3 py-5 text-white sm:py-6">
            <div class="max-w-4xl">
                <x-artwork
                    :model="$show"
                    type="logo"
                    :alt="$show->name . ' logo'"
                    class="h-24 drop-shadow sm:h-28 md:h-40"
                >
                    <flux:heading size="xl" class="font-serif tracking-wide">{{ $show->name }}</flux:heading>
                </x-artwork>
            </div>

            <div class="truncate">
                <flux:heading size="xl" class="inline font-serif tracking-wide">{{ $show->name }}</flux:heading>
            </div>

            <div class="truncate text-zinc-200">
                @if ($this->yearLabel())
                    <span>{{ $this->yearLabel() }}</span>
                @endif

                @if ($show->status)
                    @if ($this->yearLabel())
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span class="{{ $show->status->iconColorClass() }} inline-flex items-center gap-1 align-middle">
                        <x-dynamic-component :component="'flux::icon.' . $show->status->icon()" variant="mini" />
                        {{ $show->status->getLabel() }}
                    </span>
                @endif

                @if ($this->isScheduleVisible())
                    <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    <span>{{ $this->scheduleLabel() }}</span>
                @endif
            </div>

            @if ($show->genres && count($show->genres))
                <div class="flex gap-4 truncate text-zinc-200">
                    @foreach ($show->genres as $genre)
                        <span class="inline-flex items-center gap-1 align-middle">
                            <x-dynamic-component
                                :component="'flux::icon.' . \App\Enums\Genre::iconFor($genre)"
                                variant="mini"
                            />
                            {{ \App\Enums\Genre::labelFor($genre) }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="truncate text-sm text-zinc-200">
                @if ($this->runtime())
                    <span>{{ $this->runtime() }}</span>
                @endif

                @foreach ($this->networkInfoItems() as $info)
                    @if ($this->runtime() || ! $loop->first)
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    @if ($info['logoUrl'])
                        <flux:tooltip :content="$info['tooltip']">
                            <img
                                src="{{ $info['logoUrl'] }}"
                                alt="{{ $info['tooltip'] }}"
                                class="inline-block h-5 w-auto object-contain align-middle"
                            />
                        </flux:tooltip>
                    @else
                        <span>{{ $info['tooltip'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-8">
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
