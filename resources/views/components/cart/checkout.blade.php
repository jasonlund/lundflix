<?php

use App\Actions\Request\CreateRequest;
use App\Actions\Request\CreateRequestItems;
use App\Enums\MediaType;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\CartService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    #[Computed]
    public function cartItems(): Collection
    {
        return app(CartService::class)->loadItems();
    }

    #[Computed]
    public function cartCount(): int
    {
        return app(CartService::class)->count();
    }

    public function removeItem(string $type, int $id): void
    {
        if (! in_array($type, ['movie', 'episode'], true)) {
            return;
        }

        $model = $type === 'movie' ? Movie::find($id) : Episode::find($id);

        if ($model) {
            app(CartService::class)->remove($model);
            unset($this->cartItems, $this->cartCount);
            $this->dispatch('cart-updated');
        }
    }

    public function submit(CreateRequest $createRequest, CreateRequestItems $createRequestItems): void
    {
        $this->validate();

        $cart = app(CartService::class);

        if ($cart->isEmpty()) {
            $this->addError('cart', 'Your cart is empty.');

            return;
        }

        DB::transaction(function () use ($cart, $createRequest, $createRequestItems) {
            $request = $createRequest->create(Auth::user(), $this->notes ?: null);

            $items = $cart
                ->loadItems()
                ->map(
                    fn ($item) => [
                        'type' => $item->getMediaType(),
                        'id' => $item->id,
                    ],
                )
                ->all();

            $createRequestItems->create($request, $items);

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

    @if ($this->cartItems->isEmpty())
        <div class="rounded-lg bg-zinc-800 p-8 text-center">
            <flux:icon name="shopping-cart" class="mx-auto mb-4 size-12 text-zinc-500" />
            <flux:heading size="lg">Your cart is empty</flux:heading>
            <flux:text class="mt-2 text-zinc-400">Search for movies and shows to add to your request.</flux:text>
            <flux:button as="a" href="{{ route('home') }}" variant="primary" class="mt-4">Browse Content</flux:button>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($this->cartItems as $item)
                <div
                    wire:key="checkout-{{ $item->getMediaType()->getLabel() }}-{{ $item->id }}"
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
            <flux:field>
                <flux:textarea
                    wire:model.blur="notes"
                    label="Notes (optional)"
                    placeholder="Any special requests or notes..."
                    rows="3"
                />
                <flux:error name="notes" />
            </flux:field>

            <flux:button wire:click="submit" variant="primary" class="w-full">
                Submit Request ({{ $this->cartCount }} items)
            </flux:button>
        </div>
    @endif
</div>
