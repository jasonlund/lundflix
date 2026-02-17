<?php

use App\Models\Movie;
use App\Services\CartService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Movie $movie;

    public bool $showText = true;

    public bool $inCart = false;

    public function mount(CartService $cart): void
    {
        $this->inCart = $cart->has($this->movie->id);
    }

    #[Computed]
    public function isDisabled(): bool
    {
        $status = $this->movie->status;

        if ($status === null) {
            return false;
        }

        return ! $status->isCartable();
    }

    public function toggle(CartService $cart): void
    {
        if ($this->isDisabled) {
            return;
        }

        $this->inCart = $cart->toggleMovie($this->movie->id);
        $this->dispatch('cart-updated');
    }
};
?>

<div>
    @if ($this->isDisabled)
        <flux:tooltip content="Not yet released">
            <div>
                <flux:button disabled icon="plus" size="sm">
                    @if ($showText)
                        Add to Cart
                    @endif
                </flux:button>
            </div>
        </flux:tooltip>
    @else
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
    @endif
</div>
