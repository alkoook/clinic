@props(['name'])

<svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    {{ $attributes }}
>
    @switch($name)
        @case('dashboard')
            <path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z" />
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="3" /><path d="M8 3v4m8-4v4M3 10h18m-14 4h3m4 0h3m-10 4h3" />
            @break
        @case('calendar-days')
            <rect x="3" y="5" width="18" height="16" rx="3" /><path d="M8 3v4m8-4v4M3 10h18" /><path d="M7.5 14h.01m4.49 0h.01m4.49 0h.01M7.5 17.5h.01m4.49 0h.01m4.49 0h.01" />
            @break
        @case('sessions')
            <path d="M8 3h8m-9 3h10a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" /><path d="m9 14 2 2 4-5" />
            @break
        @case('patients')
            <path d="M16 20v-1.5A3.5 3.5 0 0 0 12.5 15h-5A3.5 3.5 0 0 0 4 18.5V20" /><circle cx="10" cy="8" r="3" /><path d="M18 8v6m-3-3h6" />
            @break
        @case('patient-add')
            <circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1.5A3.5 3.5 0 0 1 7 15h4a3.5 3.5 0 0 1 3.5 3.5V20M18 7v6m-3-3h6" />
            @break
        @case('practitioners')
            <path d="M6 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" /><circle cx="12" cy="7" r="4" /><path d="M9.5 19h5M12 16.5v5" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('chart')
            <path d="M4 20V10m6 10V4m6 16v-7m4 7H2" />
            @break
        @case('expenses')
            <path d="M4 7h16v12H4zM7 7V5h10v2" /><path d="M8 13h8m-4-3v6" />
            @break
        @case('agreements')
            <path d="M7 3h8l4 4v14H7z" /><path d="M15 3v5h5M10 12h6m-6 4h6" />
            @break
        @case('cashbox')
            <rect x="3" y="6" width="18" height="13" rx="3" /><path d="M3 10h18m-5 4h2" />
            @break
        @case('settlements')
            <path d="M3 7h18M6 3v4m12-4v4M5 11h14v9H5z" /><path d="m9 15 2 2 4-4" />
            @break
        @case('reports')
            <path d="M5 3h14v18H5z" /><path d="M9 17v-3m3 3V9m3 8v-5" />
            @break
        @case('specialties')
            <path d="M12 3v18M3 12h18" /><circle cx="12" cy="12" r="9" />
            @break
        @case('treatment')
            <path d="M8.5 4.5 19.5 15.5a3 3 0 0 1-4 4L4.5 8.5a3 3 0 0 1 4-4Z" /><path d="m8 12 4-4" />
            @break
        @case('rooms')
            <path d="M4 21V4h12v17M4 21h16M9 12h.01M16 8h4v13" />
            @break
        @case('users')
            <circle cx="9" cy="8" r="3" /><path d="M3 20v-1.5A3.5 3.5 0 0 1 6.5 15h5a3.5 3.5 0 0 1 3.5 3.5V20M16 5.5a3 3 0 0 1 0 5.8m1 3.9a3.5 3.5 0 0 1 4 3.5V20" />
            @break
        @case('audit')
            <path d="M12 3 4 6v5c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-3Z" /><path d="m9 12 2 2 4-4" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z" />
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
            @break
        @case('moon')
            <path d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5a8.5 8.5 0 1 0 11.7 11.7Z" />
            @break
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('close')
            <path d="m6 6 12 12M18 6 6 18" />
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5m5 5H3m12-8h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" />
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />
            @break
        @case('eye')
            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" />
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 6.2A10.6 10.6 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3.1 3.8M6.2 6.8C3.9 8.2 2.5 12 2.5 12s3.5 6 9.5 6c1 0 1.9-.2 2.7-.5" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            @break
        @case('check')
            <circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" />
            @break
        @case('warning')
            <path d="M10.3 4.1 2.6 18a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 4.1a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4m0 4h.01" />
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" />
            @break
        @default
            <circle cx="12" cy="12" r="9" /><path d="M12 8v4m0 4h.01" />
    @endswitch
</svg>
