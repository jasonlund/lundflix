@props([
    'icon' => 'exclamation-triangle',
    'bag' => 'default',
    'message' => null,
    'nested' => true,
    'name' => null,
])

@php
$errorBag = $errors->getBag($bag);
$message ??= $name ? $errorBag->first($name) : null;

if ($name && (is_null($message) || $message === '') && filter_var($nested, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false) {
    $message = $errorBag->first($name . '.*');
}

$classes = Flux::classes()
    ->add($message ? '' : 'hidden');
@endphp

<x-lundbergh-bubble
    variant="error"
    role="alert"
    aria-live="polite"
    aria-atomic="true"
    data-flux-error
    data-flux-error-bubble
    {{ $attributes->class($classes) }}
>
    <?php if ($message) : ?>
        {{ $message }}
    <?php endif; ?>
</x-lundbergh-bubble>
