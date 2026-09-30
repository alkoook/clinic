@props(['compact' => false])

@php
    $homeUrl = \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/');
@endphp

<a
    href="{{ $homeUrl }}"
    {{ $attributes->class(['group inline-flex items-center gap-3 rounded-2xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--focus-ring)]']) }}
    @if (\Illuminate\Support\Facades\Route::has('dashboard')) wire:navigate @endif
>
    <span class="brand-mark relative grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-[var(--brand)] text-white shadow-[0_10px_28px_-12px_var(--brand-shadow)]">
        @if (filled($clinicLogoUrl ?? null))
            <img src="{{ $clinicLogoUrl }}" alt="شعار {{ $clinicName }}" class="size-full object-cover">
        @else
        <svg viewBox="0 0 48 48" aria-hidden="true" class="size-9" fill="none">
            <path d="M24 39.5c-7.4-5.6-13-11-13-18.1A8.6 8.6 0 0 1 19.6 13c2 0 3.8.7 5.2 2 1.5-1.3 3.3-2 5.3-2a8.6 8.6 0 0 1 8.6 8.4c0 7.1-5.5 12.5-14.7 18.1Z" fill="currentColor" opacity=".2"/>
            <path d="M24 38.5c-7.4-5.6-12-10.4-12-16.9a7.8 7.8 0 0 1 13-5.7 7.8 7.8 0 0 1 13 5.7c0 6.5-4.6 11.3-14 16.9Z" stroke="currentColor" stroke-width="2.3" stroke-linejoin="round"/>
            <path d="M24.8 18.5v12M18.8 24.5h12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
        @endif
        <span class="absolute -bottom-4 -left-3 size-9 rounded-full bg-white/15"></span>
    </span>

    @unless ($compact)
        <span class="min-w-0 text-right">
            <span class="block truncate text-sm font-extrabold tracking-tight text-[var(--ink)]">{{ $clinicName ?? config('clinic.name') }}</span>
        </span>
    @endunless
</a>
