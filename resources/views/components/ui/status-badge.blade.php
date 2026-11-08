@props(['status' => null, 'inProgress' => false])

@php
    $label = $inProgress ? 'قيد العلاج' : $status->label();
    $badgeClass = $inProgress ? 'status-info' : $status->badgeClass();
    $icon = $inProgress ? 'clock' : $status->icon();
@endphp

<span {{ $attributes->class(['status-badge', $badgeClass]) }}>
    <x-ui.icon :name="$icon" class="size-3.5 shrink-0" />
    <span>{{ $label }}</span>
</span>
