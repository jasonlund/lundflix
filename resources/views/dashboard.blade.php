<x-layouts.app>
    <div class="flex min-h-screen flex-col items-center justify-center gap-8">
        <flux:heading size="xl">Dashboard</flux:heading>

        <livewire:media-search />

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" size="sm">Logout</flux:button>
        </form>
    </div>
</x-layouts.app>
