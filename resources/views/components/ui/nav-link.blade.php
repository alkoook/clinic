@props([
    'href',
    'active' => false,
])

<a
    href="{{ $href }}"
    wire:navigate
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'nav-link',
        'nav-link-active' => $active,
    ]) }}
>
    {{ $slot }}
</a>
