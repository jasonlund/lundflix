<?php

use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Services\CartService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $notes = '';

    public function items(CartService $cart): Collection
    {
        return $cart->loadItems();
    }

    public function count(CartService $cart): int
    {
        return $cart->count();
    }

    public function removeItem(string $type, int $id, CartService $cart): void
    {
        $model = $type === 'movie' ? Movie::find($id) : \App\Models\Episode::find($id);

        if ($model) {
            $cart->remove($model);
            $this->dispatch('cart-updated');
        }
    }

    public function submit(CartService $cart): void
    {
        $this->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($cart->isEmpty()) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        DB::transaction(function () use ($cart) {
            $request = Request::create([
                'user_id' => Auth::id(),
                'status' => 'pending',
                'notes' => $this->notes ?: null,
            ]);

            foreach ($cart->loadItems() as $item) {
                RequestItem::create([
                    'request_id' => $request->id,
                    'requestable_type' => $item::class,
                    'requestable_id' => $item->id,
                ]);
            }

            $cart->clear();
        });

        $this->dispatch('cart-updated');

        session()->flash('message', 'Your request has been submitted!');
        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <flux:button as="a" href="{{ route('home') }}" variant="ghost" icon="arrow-left" />
        <flux:heading size="xl">Checkout</flux:heading>
    </div>

    @if (session('message'))
        <flux:callout variant="success">{{ session('message') }}</flux:callout>
    @endif

    @error('cart')
        <flux:callout variant="danger">{{ $message }}</flux:callout>
    @enderror

    @php($cartItems = $this->items(app(CartService::class)))

    @if ($cartItems->isEmpty())
        <div class="rounded-lg bg-zinc-800 p-8 text-center">
            <flux:icon name="shopping-cart" class="mx-auto mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg">Your cart is empty</flux:heading>
            <flux:text class="mt-2 text-zinc-400">Search for movies and shows to add to your request.</flux:text>
            <flux:button as="a" href="{{ route('home') }}" variant="primary" class="mt-4">Browse Content</flux:button>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($cartItems as $item)
                <div
                    wire:key="checkout-{{ class_basename($item) }}-{{ $item->id }}"
                    class="flex items-center gap-4 rounded-lg bg-zinc-800 p-4"
                >
                    <flux:icon
                        name="{{ $item instanceof Movie ? 'film' : 'tv' }}"
                        class="size-6 shrink-0 text-zinc-400"
                    />
                    <div class="min-w-0 flex-1">
                        <flux:text class="font-medium">
                            @if ($item instanceof Movie)
                                {{ $item->title }}
                                @if ($item->year)
                                    <span class="text-zinc-400">({{ $item->year }})</span>
                                @endif
                            @else
                                {{ $item->show->name }}
                                <span class="text-zinc-400">
                                    - S{{ $item->season }}E{{ $item->number }}: {{ $item->name }}
                                </span>
                            @endif
                        </flux:text>
                    </div>
                    <flux:button
                        wire:click="removeItem('{{ $item instanceof Movie ? 'movie' : 'episode' }}', {{ $item->id }})"
                        variant="ghost"
                        icon="x-mark"
                        size="sm"
                    />
                </div>
            @endforeach
        </div>

        <div class="space-y-4 pt-4">
            <flux:textarea
                wire:model="notes"
                label="Notes (optional)"
                placeholder="Any special requests or notes..."
                rows="3"
            />

            <flux:button wire:click="submit" variant="primary" class="w-full">
                Submit Request ({{ $this->count(app(CartService::class)) }} items)
            </flux:button>
        </div>
    @endif
</div>
