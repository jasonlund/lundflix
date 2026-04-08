<?php

use App\Models\Movie;
use App\Models\Subscription;
use App\Support\Formatters;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public bool $isSubscribed = false;

    public function mount(Movie $movie): void
    {
        $this->movie = $movie;
        $this->isSubscribed =
            auth()->check() &&
            Subscription::query()
                ->where('user_id', auth()->id())
                ->where('subscribable_type', Movie::class)
                ->where('subscribable_id', $this->movie->id)
                ->exists();
    }

    #[Computed]
    public function isSubscribable(): bool
    {
        $status = $this->movie->status;

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
                ->where('subscribable_type', Movie::class)
                ->where('subscribable_id', $this->movie->id)
                ->delete();
            $this->isSubscribed = false;
        } else {
            Subscription::create([
                'user_id' => $userId,
                'subscribable_type' => Movie::class,
                'subscribable_id' => $this->movie->id,
            ]);
            $this->isSubscribed = true;
        }

        Flux::toast(
            text: __($this->isSubscribed ? 'lundbergh.toast.subscribed' : 'lundbergh.toast.unsubscribed', [
                'title' => $this->movie->title,
            ]),
        );
    }

    #[Computed]
    public function isCartDisabled(): bool
    {
        $status = $this->movie->status;

        if ($status === null) {
            return false;
        }

        return ! $status->isCartable();
    }

    #[Computed]
    public function releaseDate(): ?string
    {
        if ($this->movie->release_date) {
            return $this->movie->release_date->format('m/d/y');
        }

        if ($this->movie->year) {
            return (string) $this->movie->year;
        }

        return null;
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->movie->artUrl('logo');
    }

    #[Computed]
    public function backgroundUrl(): ?string
    {
        return $this->movie->artUrl('background');
    }

    #[Computed]
    public function contentRating(): ?string
    {
        return $this->movie->contentRating();
    }

    #[Computed]
    public function formattedRuntime(): ?string
    {
        return Formatters::runtime($this->movie->runtime);
    }

    public function render(): mixed
    {
        return $this->view()
            ->layout('components.layouts.app', [
                'backgroundImage' => $this->backgroundUrl(),
            ])
            ->title($this->movie->title);
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
            @elseif ($movie->status !== null && ! $movie->status->isCartable())
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
                x-data="{
                    get inCart() {
                        return $store.cart.hasMovie({{ $movie->id }})
                    },
                    addedMsg:
                        {{ Js::from(__('lundbergh.toast.cart_added', ['title' => $movie->title])) }},
                    removedMsg:
                        {{ Js::from(__('lundbergh.toast.cart_removed', ['title' => $movie->title])) }},
                }"
            >
                @if ($this->isCartDisabled)
                    <flux:tooltip content="Not yet released">
                        <div
                            class="flex items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white/50 backdrop-blur-sm"
                        >
                            <div class="relative flex min-w-4 items-center justify-center">
                                <span>+</span>
                            </div>
                            <flux:icon.shopping-cart class="size-4" />
                        </div>
                    </flux:tooltip>
                @else
                    <flux:tooltip x-bind:content="inCart ? 'Remove from Cart' : 'Add to Cart'">
                        <button
                            x-on:click="
                                $store.cart.toggleMovie({{ $movie->id }})
                                $dispatch('cart-movie-toggled', {
                                    text: $store.cart.hasMovie({{ $movie->id }}) ? addedMsg : removedMsg,
                                })
                            "
                            class="flex cursor-pointer items-center gap-1.5 rounded-lg border-1 border-zinc-600 bg-white/10 px-3 py-2 text-white backdrop-blur-sm transition hover:bg-white/20"
                        >
                            <div class="relative flex min-w-4 items-center justify-center">
                                <span class="invisible">+</span>
                                <span x-show="inCart" x-cloak class="absolute">
                                    <flux:icon.check class="size-4" />
                                </span>
                                <span x-show="!inCart" class="absolute">+</span>
                            </div>
                            <flux:icon.shopping-cart class="size-4" />
                        </button>
                    </flux:tooltip>
                @endif
            </div>
        </div>

        <div class="relative flex flex-col gap-3 py-5 text-white sm:py-6">
            <div class="max-w-4xl">
                <x-artwork
                    :model="$movie"
                    type="logo"
                    :alt="$movie->title . ' logo'"
                    :fallback="false"
                    class="h-24 drop-shadow sm:h-28 md:h-40"
                />
            </div>

            <div class="{{ $this->logoUrl ? '' : 'flex h-[128px] items-end sm:h-[144px] md:h-[192px]' }}">
                <flux:heading
                    size="xl"
                    class="{{ $this->logoUrl ? 'truncate' : 'line-clamp-2 text-5xl' }} font-serif tracking-wide"
                >
                    {{ $movie->title }}
                </flux:heading>
                @if ($movie->original_title && $movie->original_title !== $movie->title)
                    <span class="ml-3 text-base">{{ $movie->original_title }}</span>
                @endif
            </div>

            <div class="truncate text-zinc-200">
                @if ($this->releaseDate())
                    <span>{{ $this->releaseDate() }}</span>
                @endif

                @if ($movie->status)
                    @if ($this->releaseDate())
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span class="{{ $movie->status->iconColorClass() }} inline-flex items-center gap-1 align-middle">
                        <x-dynamic-component :component="'flux::icon.' . $movie->status->icon()" variant="mini" />
                        {{ $movie->status->getLabel() }}
                    </span>
                @endif
            </div>

            @if ($movie->genres && count($movie->genres))
                <div class="flex gap-4 truncate text-zinc-200">
                    @foreach ($movie->genres as $genre)
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
                @if ($movie->original_language)
                    <span>{{ $movie->original_language->getLabel() }}</span>
                @endif

                @if ($this->formattedRuntime())
                    @if ($movie->original_language)
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span>{{ $this->formattedRuntime() }}</span>
                @endif

                @if ($this->contentRating())
                    @if ($movie->original_language || $this->formattedRuntime())
                        <span class="text-zinc-500">&nbsp;&middot;&nbsp;</span>
                    @endif

                    <span>{{ $this->contentRating() }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-8">
        @if ($movie->imdb_id)
            <livewire:movies.plex-availability :movie="$movie" lazy />
        @endif

        <livewire:movies.predb-releases :movie="$movie" lazy />
    </div>
</div>
