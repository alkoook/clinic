<button
    type="button"
    data-theme-toggle
    aria-label="تفعيل المظهر الداكن"
    aria-pressed="false"
    title="تبديل المظهر"
    {{ $attributes->merge(['class' => 'icon-btn']) }}
>
    <x-ui.icon name="moon" class="theme-icon-dark size-5" />
    <x-ui.icon name="sun" class="theme-icon-light hidden size-5" />
</button>
