<?php

use App\Models\Movie;
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
            $this->movie
                ->subscriptions()
                ->where('user_id', auth()->id())
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
        if (! auth()->check() || ! $this->isSubscribable) {
            return;
        }

        $userId = auth()->id();

        if ($this->isSubscribed) {
            $this->movie
                ->subscriptions()
                ->where('user_id', $userId)
                ->delete();
            $this->isSubscribed = false;
        } else {
            $this->movie->subscriptions()->firstOrCreate(['user_id' => $userId]);
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
    <x-media-hero :model="$movie" :title="$movie->title" :logo-url="$this->logoUrl">
        <x-slot:subtitle>
            @if ($movie->original_title && $movie->original_title !== $movie->title)
                <span class="block text-center text-sm">{{ $movie->original_title }}</span>
            @endif
        </x-slot>

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
                        aria-label="{{ $isSubscribed ? 'Unsubscribe from ' . $movie->title : 'Subscribe to ' . $movie->title }}"
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
                        <button
                            disabled
                            aria-disabled="true"
                            class="flex cursor-not-allowed items-center gap-2 rounded-full border-1 border-zinc-600 bg-white/10 px-5 py-3 text-sm font-medium text-white/50 backdrop-blur-sm sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-xs"
                        >
                            <flux:icon.plus class="size-5 sm:size-4" />
                            <span>Cart</span>
                        </button>
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
                            x-bind:class="
                                inCart
                                    ? 'bg-lundflix/20 border-lundflix hover:bg-lundflix/30 text-white'
                                    : 'border-zinc-600 bg-white/10 text-white hover:bg-white/20'
                            "
                            x-bind:aria-label="inCart ? 'Remove from Cart' : 'Add to Cart'"
                            class="focus-visible:ring-lundflix flex cursor-pointer items-center gap-2 rounded-full border-1 px-5 py-3 text-sm font-medium backdrop-blur-sm transition focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-zinc-950 focus-visible:outline-none sm:gap-1.5 sm:px-4 sm:py-2.5 sm:text-xs"
                        >
                            <div class="relative flex items-center justify-center">
                                <span x-show="inCart" x-cloak>
                                    <flux:icon.check class="size-5 sm:size-4" />
                                </span>
                                <span x-show="!inCart">
                                    <flux:icon.plus class="size-5 sm:size-4" />
                                </span>
                            </div>
                            <span>Cart</span>
                        </button>
                    </flux:tooltip>
                @endif
            </div>
        </x-slot>

        <x-slot:metadata>
            @php
                $hasPrevious = false;
            @endphp

            @if ($movie->original_language)
                @if ($hasPrevious)
                    <x-middot />
                @endif

                <span>{{ $movie->original_language->getLabel() }}</span>
                @php
                    $hasPrevious = true;
                @endphp
            @endif

            @if ($this->formattedRuntime())
                @if ($hasPrevious)
                    <x-middot />
                @endif

                <span>{{ $this->formattedRuntime() }}</span>
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

        @if ($movie->genres && count($movie->genres))
            <x-slot:genres>
                @foreach ($movie->genres as $genre)
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
        @if ($movie->imdb_id)
            <livewire:movies.availability :movie="$movie" lazy />
        @endif
    </div>
</div>
