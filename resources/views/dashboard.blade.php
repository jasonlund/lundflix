<x-layouts.app :background-image="Vite::image('lundberg-background.jpg')">
    <div class="pt-5 sm:pt-6">
        <livewire:dashboard.greeting />

        <div class="mt-6 space-y-6">
            <livewire:dashboard.requests />
            <livewire:plex.server-status lazy />
        </div>
    </div>
</x-layouts.app>
