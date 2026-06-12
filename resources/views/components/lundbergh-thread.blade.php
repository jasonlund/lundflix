@props([
    'beats' => [],
    'size' => 'small',
    'variant' => 'info',
    'bubbleClass' => 'grow',
    'withMargin' => true,
])

{{--
    The first bubble shows Lundbergh's head with the tail centered on him; the rest wrap under it
    with a small indent so the first message reads as the widest. Works for any size.
--}}
<div {{ $attributes->class(($withMargin ? 'mt-3' : 'mt-0') . ' flex flex-col gap-1.5') }}>
    @foreach ($beats as $beat)
        <x-lundbergh-bubble
            contentTag="div"
            :variant="$variant"
            :size="$size"
            :withMargin="false"
            :showAvatar="$loop->first"
            :showTail="$loop->first"
            :class="$loop->first ? null : 'ps-5'"
            :bubbleClass="$bubbleClass"
        >
            {!! $beat !!}
        </x-lundbergh-bubble>
    @endforeach
</div>
