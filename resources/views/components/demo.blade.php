<?php

use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public function mount(): void
    {
        $this->addError('demo_field', "Yeah… so, this is what an error looks like. That's… not great.");
    }

    public function showToast(): void
    {
        $this->addError('demo_field', "Yeah… so, this is what an error looks like. That's… not great.");

        Flux::toast(
            text: "Mmm yeah… I'm gonna need you to go ahead and acknowledge this toast. That'd be great.",
            heading: 'Lundbergh says...',
        );
    }
};
?>

<div class="mx-auto max-w-2xl space-y-12 p-8">
    <flux:heading size="xl">Component Demo</flux:heading>

    {{-- Error Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Error</flux:heading>
        <flux:field>
            <flux:label>Demo Field</flux:label>
            <flux:input value="Something wrong" disabled />
            <flux:error name="demo_field" />
        </flux:field>
    </flux:card>

    {{-- Tooltip Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Tooltip</flux:heading>
        <div class="flex flex-wrap items-center gap-4">
            <flux:tooltip content="Lundbergh tooltip" kbd="⌘K" toggleable>
                <flux:button type="button" variant="ghost" icon="search">Hover for tooltip</flux:button>
            </flux:tooltip>
            <flux:tooltip content="Another Lundbergh bubble" toggleable>
                <flux:button type="button" variant="outline">Hover for more</flux:button>
            </flux:tooltip>
        </div>
    </flux:card>

    {{-- Toast Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Toast</flux:heading>
        <flux:button type="button" wire:click="showToast">Show Lundbergh Toast</flux:button>
    </flux:card>

    {{-- Modal Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Modal</flux:heading>
        <flux:modal.trigger name="demo-confirmation">
            <flux:button type="button">Open Lundbergh Modal</flux:button>
        </flux:modal.trigger>

        <flux:modal name="demo-confirmation" class="w-full max-w-md">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Lundbergh confirmation</flux:heading>
                    <flux:text class="mt-2">Lundbergh wants to make sure you are ready to proceed.</flux:text>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" size="xs">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" size="xs">Continue</flux:button>
                </div>
            </div>
        </flux:modal>
    </flux:card>

    {{-- Command Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Command (Empty State)</flux:heading>
        <div class="w-full max-w-md">
            <flux:command>
                <flux:command.input placeholder="Search titles..." />
                <flux:command.items></flux:command.items>
            </flux:command>
        </div>
    </flux:card>

    {{-- Callout Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Callout</flux:heading>
        <div class="flex flex-col gap-4">
            <flux:callout>
                <flux:callout.heading icon="sparkles">Lundbergh says...</flux:callout.heading>
                <flux:callout.text>Keep your request list tidy so it is easy to triage.</flux:callout.text>
            </flux:callout>

            <flux:callout variant="success">
                <flux:callout.heading icon="check-circle">Request received</flux:callout.heading>
                <flux:callout.text>We will reach out when your titles are ready to stream.</flux:callout.text>
            </flux:callout>

            <flux:callout variant="danger">
                <flux:callout.heading icon="exclamation-triangle">Something went wrong</flux:callout.heading>
                <flux:callout.text>Please try again in a moment.</flux:callout.text>
            </flux:callout>
        </div>
    </flux:card>

    {{-- Skeleton Component --}}
    <flux:card class="space-y-4">
        <flux:heading size="lg">Skeleton</flux:heading>
        <flux:skeleton />
    </flux:card>
</div>
