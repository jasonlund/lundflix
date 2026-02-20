<div
    x-data="{
        query: @js($query),
        selected: @js($defaults),
        get url() {
            if (! this.query.trim() || this.selected.length === 0) {
                return ''
            }
            const cats = this.selected.map((c) => c + '=').join('&')
            return (
                'https://iptorrents.com/t?' +
                cats +
                '&q=' +
                encodeURIComponent(this.query.trim()) +
                '&qf=#torrents'
            )
        },
        toggleAll(allValues) {
            if (this.selected.length === allValues.length) {
                this.selected = []
            } else {
                this.selected = [...allValues]
            }
        },
        insertSuggestion(value) {
            const input = this.$refs.queryInput
            const start = input.selectionStart
            const end = input.selectionEnd

            if (start !== end) {
                this.query =
                    this.query.slice(0, start) + value + this.query.slice(end)
                this.$nextTick(() => {
                    const pos = start + value.length
                    input.setSelectionRange(pos, pos)
                })
            } else {
                this.query = this.query.trim()
                    ? this.query.trimEnd() + ' ' + value
                    : value
                this.$nextTick(() =>
                    input.setSelectionRange(this.query.length, this.query.length),
                )
            }
        },
    }"
    class="space-y-4"
>
    {{-- Search Query --}}
    <div>
        <label class="text-sm font-medium text-white">Search Query</label>
        <x-filament::input.wrapper>
            <x-filament::input type="text" x-ref="queryInput" x-model="query" />
        </x-filament::input.wrapper>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
            @foreach ($suggestions as $suggestion)
                <x-filament::badge
                    tag="button"
                    type="button"
                    color="gray"
                    size="sm"
                    x-on:mousedown.prevent
                    x-on:click="insertSuggestion({{ Js::from($suggestion) }})"
                    class="font-mono"
                >
                    {{ $suggestion }}
                </x-filament::badge>
            @endforeach
        </div>
    </div>

    {{-- Categories --}}
    <div>
        <div class="flex items-center justify-between">
            <label class="text-sm font-medium text-white">Categories</label>
            <x-filament::link
                tag="button"
                type="button"
                color="primary"
                size="sm"
                x-on:click="toggleAll({{ Js::from(array_map('intval', array_keys($categories))) }})"
            >
                <span x-text="selected.length === {{ count($categories) }} ? 'Deselect all' : 'Select all'"></span>
            </x-filament::link>
        </div>
        <div class="mt-2 grid grid-cols-3 gap-2">
            @foreach ($categories as $value => $label)
                <label class="flex items-center gap-2">
                    <x-filament::input.checkbox :value="$value" x-model.number="selected" />
                    <span class="text-sm text-gray-300">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- URL --}}
    <div>
        <label class="text-sm font-medium text-white">URL</label>
        <div class="flex items-center gap-2">
            <x-filament::input.wrapper disabled class="flex-1">
                <x-filament::input type="text" x-bind:value="url" readonly class="font-mono text-xs" />
            </x-filament::input.wrapper>
            <x-filament::icon-button
                tag="a"
                href="#"
                x-bind:href="url"
                x-show="url !== ''"
                target="_blank"
                rel="noopener noreferrer"
                icon="lucide-external-link"
                color="gray"
                label="Open in New Tab"
            />
        </div>
        <p x-show="url === ''" x-cloak class="mt-1 text-sm text-gray-400">
            Enter a query and select at least one category.
        </p>
    </div>
</div>
