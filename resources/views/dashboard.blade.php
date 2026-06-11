<x-layouts.app title="Dashboard">
    <div class="pt-5 sm:pt-6">
        <livewire:dashboard.greeting />

        <div class="mt-6 space-y-6">
            @php($user = auth()->user())
            @if ($user->requests()->exists() || $user->subscriptions()->exists())
                <livewire:dashboard.requests />
                <livewire:dashboard.subscriptions />
            @endif

            <livewire:plex.server-status />
        </div>
    </div>
</x-layouts.app>
