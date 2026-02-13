<?php

use App\Models\Movie;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public bool $showText = true;

    public bool $inCart = false;

    public function mount(CartService $cart): void
    {
        $this->inCart = $cart->has($this->movie->id);
    }

    public function toggle(CartService $cart): void
    {
        $this->inCart = $cart->toggleMovie($this->movie->id);
        $this->dispatch('cart-updated');

        Flux::toast(
            text: __($this->inCart ? 'lundbergh.toast.cart_added' : 'lundbergh.toast.cart_removed', [
                'title' => $this->movie->title,
            ]),
        );
    }
};
?>

<div>
    <flux:button
        wire:click="toggle"
        :variant="$inCart ? 'danger' : 'primary'"
        :icon="$inCart ? 'minus' : 'plus'"
        size="sm"
    >
        @if ($showText)
            {{ $inCart ? 'Remove' : 'Add to Cart' }}
        @endif
    </flux:button>
</div>
