<x-layouts.app :background-image="Vite::image('lundberg-background.jpg')">
    <div class="pt-5 sm:pt-6">
        <x-lundbergh-bubble contentTag="div">
            {!! __('lundbergh.dashboard.' . (auth()->user()->requests()->exists() ? 'greeting' : 'greeting_new')) !!}
        </x-lundbergh-bubble>

        <div class="mt-6">
            <livewire:plex.server-status lazy />
        </div>
    </div>
</x-layouts.app>
