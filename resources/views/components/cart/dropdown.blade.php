<?php

use App\Services\CartService;
use App\Support\RequestItemFormatter;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $itemCount = 0;

    public function mount(CartService $cart): void
    {
        $this->itemCount = $cart->count();
    }

    #[On('cart-updated')]
    public function refreshCount(CartService $cart): void
    {
        $this->itemCount = $cart->count();
        $this->js('$data.syncing = false');
    }

    #[Computed]
    public function groupedCartItems(): array
    {
        return app(CartService::class)->loadGroupedItems();
    }

    public function formatRun(Collection $episodes): string
    {
        return RequestItemFormatter::formatRun($episodes);
    }

    public function formatSeason(int $season): string
    {
        return RequestItemFormatter::formatSeason($season);
    }

    /**
     * Sync episodes for a show - replaces all episodes for that show.
     *
     * @param  array<int, string>  $episodeCodes
     */
    #[On('sync-show-episodes-to-cart')]
    public function syncShowEpisodes(int $showId, array $episodeCodes): void
    {
        app(CartService::class)->syncShowEpisodes($showId, $episodeCodes);
        $this->dispatch('cart-updated');
    }

    /**
     * Toggle a movie in/out of the cart by ID.
     */
    #[On('toggle-movie-in-cart')]
    public function toggleMovieInCart(int $movieId): void
    {
        app(CartService::class)->toggleMovie($movieId);
        $this->dispatch('cart-updated');
    }
};
?>

<div x-data="{ syncing: false }" @cart-syncing.window="syncing = true">
    <flux:dropdown align="end">
        <flux:button variant="ghost" ::disabled="syncing">
            <flux:icon.loading x-show="syncing" x-cloak class="size-4" />
            <flux:icon
                name="shopping-cart"
                x-show="!syncing && !$wire.itemCount"
                x-cloak
                class="text-lundflix size-4"
            />
            <span
                x-show="! syncing && $wire.itemCount > 0"
                x-cloak
                class="text-lundflix inline-flex size-4 items-center justify-center text-sm font-bold tabular-nums"
            >
                {{ $itemCount }}
            </span>
            <span class="sr-only sm:not-sr-only">Cart</span>
        </flux:button>

        <flux:popover class="w-80">
            @if ($itemCount === 0)
                <div class="p-4">
                    <x-lundbergh-bubble size="sm" :with-margin="false">
                        {{ __('lundbergh.empty.cart_dropdown') }}
                    </x-lundbergh-bubble>
                </div>
            @else
                <div class="max-h-64 space-y-2 overflow-y-auto p-2">
                    {{-- Movies --}}
                    @foreach ($this->groupedCartItems['movies'] as $movie)
                        <div
                            wire:key="cart-item-movie-{{ $movie->id }}"
                            class="flex items-center gap-3 rounded-lg bg-zinc-800 p-2"
                        >
                            <flux:icon name="film" class="size-5 shrink-0 text-zinc-400" />
                            <div class="min-w-0 flex-1">
                                <flux:text class="truncate font-medium">{{ $movie->title }}</flux:text>
                            </div>
                        </div>
                    @endforeach

                    {{-- Shows --}}
                    @foreach ($this->groupedCartItems['shows'] as $showGroup)
                        <div wire:key="cart-item-show-{{ $showGroup['show']->id }}" class="rounded-lg bg-zinc-800 p-2">
                            <div class="flex items-center gap-3">
                                <flux:icon name="tv" class="size-5 shrink-0 text-zinc-400" />
                                <div class="min-w-0 flex-1">
                                    <flux:text class="truncate font-medium">{{ $showGroup['show']->name }}</flux:text>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($showGroup['seasons'] as $seasonData)
                                            @if ($seasonData['is_full'])
                                                <flux:badge size="sm" color="zinc">
                                                    {{ $this->formatSeason($seasonData['season']) }}
                                                </flux:badge>
                                            @else
                                                @foreach ($seasonData['runs'] as $run)
                                                    <flux:badge size="sm" color="zinc">
                                                        {{ $this->formatRun($run) }}
                                                    </flux:badge>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:separator class="my-2" />

                <div class="p-2">
                    <flux:button as="a" href="{{ route('cart.checkout') }}" variant="primary" class="w-full">
                        Checkout ({{ $itemCount }})
                    </flux:button>
                </div>
            @endif
        </flux:popover>
    </flux:dropdown>
</div>
