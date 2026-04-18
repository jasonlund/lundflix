<x-layouts.app title="Dashboard">
    <div class="pt-5 sm:pt-6">
        <livewire:dashboard.greeting />

        <div class="mt-6 space-y-6">
            <livewire:dashboard.requests />
            <livewire:dashboard.subscriptions />
            <livewire:plex.server-status lazy />
        </div>
    </div>
</x-layouts.app>
