<?php

use App\Models\Movie;
use App\Services\CartService;
use App\Support\CartItemFormatter;
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
    }

    #[Computed]
    public function groupedCartItems(): array
    {
        return app(CartService::class)->loadGroupedItems();
    }

    public function formatRun(Collection $episodes): string
    {
        return CartItemFormatter::formatRun($episodes);
    }

    public function formatSeason(int $season): string
    {
        return CartItemFormatter::formatSeason($season);
    }
};
?>

<div>
    <flux:dropdown align="end">
        <flux:button variant="ghost" icon="shopping-cart">
            Cart
            @if ($itemCount > 0)
                <flux:badge color="red" size="sm" class="ml-1">{{ $itemCount }}</flux:badge>
            @endif
        </flux:button>

        <flux:popover class="w-80">
            @if ($itemCount === 0)
                <div class="p-4 text-center text-zinc-500">
                    <flux:icon name="shopping-cart" class="mx-auto mb-2 size-8 text-zinc-400" />
                    <flux:text>Your cart is empty</flux:text>
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
