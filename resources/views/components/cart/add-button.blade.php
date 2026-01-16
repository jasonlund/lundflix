<?php

use App\Services\CartService;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

new class extends Component {
    /** @var Model|array{id?: int, tvmaze_id?: int, show_id?: int} */
    public Model|array $item;

    public bool $inCart = false;

    public function mount(CartService $cart): void
    {
        $this->inCart = $cart->has($this->item);
    }

    public function toggle(CartService $cart): void
    {
        if ($this->inCart) {
            $cart->remove($this->item);
            $this->inCart = false;
        } else {
            $cart->add($this->item);
            $this->inCart = true;
        }

        $this->dispatch('cart-updated');
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
        {{ $inCart ? 'Remove' : 'Add to Cart' }}
    </flux:button>
</div>
