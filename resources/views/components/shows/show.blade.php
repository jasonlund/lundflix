<?php

use App\Enums\ShowStatus;
use App\Models\Show;
use App\Support\AirDateTime;
use App\Support\Formatters;
use App\Support\UserTime;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Show $show;

    public int $totalEpisodeCount = 0;

    public bool $isSubscribed = false;

    public function mount(): void
    {
        $this->totalEpisodeCount = $this->show->episodes->count();
        $this->isSubscribed =
            auth()->check() &&
            $this->show
                ->subscriptions()
                ->where('user_id', auth()->id())
                ->exists();
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
        if (! auth()->check() || ! $this->isSubscribable) {
            return;
        }

        $userId = auth()->id();

        if ($this->isSubscribed) {
            $this->show
                ->subscriptions()
                ->where('user_id', $userId)
                ->delete();
            $this->isSubscribed = false;
        } else {
            $this->show->subscriptions()->firstOrCreate(['user_id' => $userId]);
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
    public function runtime(): ?string
    {
        return Formatters::runtimeFor($this->show);
    }

    #[Computed]
    public function contentRating(): ?string
    {
        return $this->show->contentRating();
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

        $schedule = AirDateTime::adjustSchedule($this->show->schedule, $this->show->web_channel);

        $days = $schedule['days'];
        $time = $schedule['time'] ?? '';
        $sourceTz = $this->scheduleTimezone();

        $dayOffset = 0;
        $timeLabel = null;

        if ($time !== '') {
            if ($sourceTz) {
                $result = UserTime::convertAirtimeWithDayOffset($time, $sourceTz);
                $timeLabel = $result['time'];
                $dayOffset = $result['dayOffset'];
            } else {
                $timeLabel = $this->formatCompactTime($time);
            }
        }

        if ($dayOffset !== 0) {
            $days = array_map(
                fn (string $day) => Carbon::parse($day)
                    ->addDays($dayOffset)
                    ->format('l'),
                $days,
            );
        }

        usort($days, fn ($a, $b) => Carbon::parse($a)->dayOfWeekIso - Carbon::parse($b)->dayOfWeekIso);

        $abbrevs = array_map(fn ($d) => Carbon::parse($d)->minDayName, $days);

        $dayLabel = $this->collapseDayRanges($abbrevs);

        return $timeLabel ? "{$dayLabel} {$timeLabel}" : $dayLabel;
    }

    private function scheduleTimezone(): ?string
    {
        return AirDateTime::scheduleTimezone($this->show->network, $this->show->web_channel);
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
        return $this->view()
            ->layout('components.layouts.app', [
                'backgroundImage' => $this->backgroundUrl(),
            ])
            ->title($this->show->name);
    }
};
?>

<div class="flex flex-col">
    <x-media-hero :model="$show" :title="$show->name" :logo-url="$this->logoUrl">
        <x-slot:actions>
            @if ($this->isSubscribable)
                <div x-data="{ syncing: false }" wire:key="subscribe-{{ $isSubscribed ? 'yes' : 'no' }}">
                    <button
                        x-on:click="
                            syncing = true
                            $wire.toggleSubscription().then(() => {
                                syncing = false
                            })
                        "
                        aria-pressed="{{ $isSubscribed ? 'true' : 'false' }}"
                        aria-label="{{ $isSubscribed ? 'Unsubscribe from ' . $show->name : 'Subscribe to ' . $show->name }}"
                        class="{{ $isSubscribed ? 'bg-lundflix/20 border-lundflix hover:bg-lundflix/30 text-white' : 'border-zinc-600 bg-white/10 text-white hover:bg-white/20' }} focus-visible:ring-lundflix flex cursor-pointer items-center gap-2 rounded-full border-1 px-5 py-3 text-sm font-medium backdrop-blur-sm transition focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 focus-visible:outline-none sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-xs"
                    >
                        <div class="relative flex items-center justify-center">
                            @if ($isSubscribed)
                                <flux:icon.check x-bind:class="syncing && 'opacity-0'" class="size-5 sm:size-4" />
                            @else
                                <flux:icon.minus x-bind:class="syncing && 'opacity-0'" class="size-5 sm:size-4" />
                            @endif
                            <flux:icon.loading x-show="syncing" x-cloak class="absolute size-5 sm:size-4" />
                        </div>
                        <span x-bind:class="syncing && 'opacity-0'" aria-live="polite" x-bind:aria-busy="syncing">
                            {{ $isSubscribed ? 'Subscribed' : 'Subscribe' }}
                        </span>
                    </button>
                </div>
            @endif

            <div
                x-data="{
                    get count() {
                        return $store.cart.countForShow({{ $show->id }})
                    },
                    get isFullSeason() {
                        return (
                            {{ $totalEpisodeCount }} > 0 &&
                            this.count > 0 &&
                            this.count >= {{ $totalEpisodeCount }}
                        )
                    },
                }"
            >
                <div
                    x-bind:class="
                        count > 0
                            ? 'bg-lundflix/20 border-lundflix text-white'
                            : 'border-zinc-600 bg-white/10 text-white'
                    "
                    class="flex cursor-default items-center gap-2 rounded-full border-1 px-5 py-3 text-sm font-medium backdrop-blur-sm transition sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-xs"
                    role="status"
                    x-bind:aria-label="count > 0 ? count + ' episodes in cart' : 'No episodes in cart'"
                >
                    <div class="relative flex items-center justify-center">
                        <span x-show="isFullSeason" x-cloak>
                            <flux:icon.check class="size-5 sm:size-4" />
                        </span>
                        <span
                            x-show="count > 0 && ! isFullSeason"
                            x-cloak
                            x-text="count"
                            class="text-base font-bold tabular-nums sm:text-sm"
                        ></span>
                        <span x-show="count === 0">
                            <flux:icon.minus class="size-5 sm:size-4" />
                        </span>
                    </div>
                    <span>Cart</span>
                </div>
            </div>
        </x-slot>

        <x-slot:metadata>
            @php
                $hasPrevious = false;
            @endphp

            @if ($this->isScheduleVisible())
                @if ($hasPrevious)
                    <x-middot />
                @endif

                <span>{{ $this->scheduleLabel() }}</span>
                @php
                    $hasPrevious = true;
                @endphp
            @endif

            @foreach ($this->networkInfoItems() as $info)
                @if ($hasPrevious)
                    <x-middot />
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
                @php
                    $hasPrevious = true;
                @endphp
            @endforeach

            @if ($this->runtime())
                @if ($hasPrevious)
                    <x-middot />
                @endif

                <span>{{ $this->runtime() }}</span>
                @php
                    $hasPrevious = true;
                @endphp
            @endif

            @if ($this->contentRating())
                @if ($hasPrevious)
                    <x-middot />
                @endif

                <span>{{ $this->contentRating() }}</span>
            @endif
        </x-slot>

        @if ($show->genres && count($show->genres))
            <x-slot:genres>
                @foreach ($show->genres as $genre)
                    <span class="inline-flex items-center gap-1 align-middle">
                        <x-dynamic-component
                            :component="'flux::icon.' . \App\Enums\Genre::iconFor($genre)"
                            variant="mini"
                            aria-hidden="true"
                        />
                        {{ \App\Enums\Genre::labelFor($genre) }}
                    </span>
                @endforeach
            </x-slot>
        @endif
    </x-media-hero>

    <div class="flex flex-col gap-8">
        @if ($show->imdb_id)
            <livewire:shows.availability :show="$show" lazy />
        @endif

        @if ($this->episodes()->isNotEmpty())
            <livewire:shows.episodes :show="$show" :episodes="$this->episodes()" />
        @else
            <livewire:shows.episodes :show="$show" lazy />
        @endif
    </div>
</div>
