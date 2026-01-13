<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center">
        <div class="text-center">
            <flux:heading size="xl">Dashboard</flux:heading>

            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <flux:button type="submit" variant="primary">Logout</flux:button>
            </form>
        </div>
    </div>
</x-layouts.app>
