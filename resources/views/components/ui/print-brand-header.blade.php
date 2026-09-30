<header class="print-brand-header hidden print:flex">
    <div class="flex items-center gap-3">
        @if (filled($clinicLogoUrl ?? null))
            <img class="size-14 rounded-xl object-contain" src="{{ $clinicLogoUrl }}" alt="شعار {{ $clinicName }}">
        @else
            <div class="grid size-14 place-items-center rounded-xl border-2 border-slate-300 text-xl font-black">ح</div>
        @endif
        <div><strong class="block text-lg">{{ $clinicName ?? config('clinic.name') }}</strong><span class="text-xs text-slate-500">نظام إدارة العيادة والتقارير المالية</span></div>
    </div>
    <div class="text-left text-xs text-slate-500"><span class="block">تاريخ الطباعة</span><strong>{{ now()->format('Y/m/d H:i') }}</strong></div>
</header>
