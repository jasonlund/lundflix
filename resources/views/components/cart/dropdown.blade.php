<?php

use App\Models\Movie;
use App\Services\CartService;
use Illuminate\Support\Collection;
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

    public function cartItems(CartService $cart): Collection
    {
        return $cart->loadItems();
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
                    @foreach ($this->cartItems(app(CartService::class)) as $item)
                        <div
                            wire:key="cart-item-{{ $item->getMediaType()->getLabel() }}-{{ $item->id }}"
                            class="flex items-center gap-3 rounded-lg bg-zinc-800 p-2"
                        >
                            <flux:icon
                                name="{{ $item instanceof Movie ? 'film' : 'tv' }}"
                                class="size-5 shrink-0 text-zinc-400"
                            />
                            <div class="min-w-0 flex-1">
                                <flux:text class="truncate font-medium">
                                    @if ($item instanceof Movie)
                                        {{ $item->title }}
                                    @else
                                        {{ $item->show->name }} - S{{ $item->season }}E{{ $item->number }}
                                    @endif
                                </flux:text>
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
