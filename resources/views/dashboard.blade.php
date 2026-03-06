<x-layouts.app :background-image="Vite::image('lundberg-background.jpg')">
    <div class="pt-5 sm:pt-6">
        <flux:heading size="xl">Dashboard</flux:heading>

        <div class="mt-6">
            <livewire:plex.server-status lazy />
        </div>
    </div>
</x-layouts.app>
