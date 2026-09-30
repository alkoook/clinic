<div class="mx-auto max-w-4xl">
    <header class="page-header"><div><p class="page-kicker">تهيئة النظام</p><h1>الإعدادات</h1><p>قيم العيادة الافتراضية التي تظهر في مختلف أجزاء النظام.</p></div></header>
    <form class="app-card space-y-7 p-5 sm:p-7" wire:submit="save">
        <section><h2 class="mb-4 font-black text-[var(--ink)]">هوية العيادة</h2><div class="grid gap-4 md:grid-cols-2"><label><span class="form-label">اسم العيادة *</span><input class="form-input" wire:model="clinicName">@error('clinicName')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">الاسم المختصر *</span><input class="form-input" wire:model="clinicShortName">@error('clinicShortName')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">الهاتف</span><input class="form-input" dir="ltr" wire:model="clinicPhone">@error('clinicPhone')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">العنوان</span><input class="form-input" wire:model="clinicAddress">@error('clinicAddress')<span class="form-error">{{ $message }}</span>@enderror</label></div></section>
        <section class="border-t border-[var(--line)] pt-6"><h2 class="mb-4 font-black text-[var(--ink)]">التشغيل والمظهر</h2><div class="grid gap-4 md:grid-cols-2"><label><span class="form-label">مدة الموعد الافتراضية</span><input class="form-input" type="number" min="5" max="720" step="5" wire:model="defaultAppointmentDuration">@error('defaultAppointmentDuration')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">السمة الافتراضية</span><select class="form-select" wire:model="defaultTheme"><option value="light">فاتحة — بحري وغابة</option><option value="dark">داكنة — فحمي وناري</option></select></label></div></section>
        <section class="border-t border-[var(--line)] pt-6" aria-labelledby="working-hours-heading">
            <div class="mb-5"><p class="page-kicker">الإطار التشغيلي للحجوزات</p><h2 id="working-hours-heading" class="mt-1 font-black text-[var(--ink)]">دوام العيادة</h2><p class="mt-1 text-sm text-[var(--muted)]">يجب أن يقع الموعد بالكامل داخل دوام العيادة وتوفر المعالج.</p></div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'] as $day => $dayName)
                    <article class="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4">
                        <div class="mb-3 flex items-center justify-between gap-3"><strong class="text-[var(--ink)]">{{ $dayName }}</strong><label class="flex items-center gap-2 text-sm font-bold text-[var(--ink-soft)]"><input class="form-checkbox" type="checkbox" wire:model.live="clinicWorkingHours.{{ $day }}.enabled"><span>{{ $clinicWorkingHours[$day]['enabled'] ? 'مفتوح' : 'مغلق' }}</span></label></div>
                        <div class="grid grid-cols-2 gap-3" @class(['opacity-45' => ! $clinicWorkingHours[$day]['enabled']])><label><span class="form-label">من</span><input class="form-input" type="time" wire:model="clinicWorkingHours.{{ $day }}.start" @disabled(! $clinicWorkingHours[$day]['enabled'])></label><label><span class="form-label">إلى</span><input class="form-input" type="time" wire:model="clinicWorkingHours.{{ $day }}.end" @disabled(! $clinicWorkingHours[$day]['enabled'])></label></div>
                    </article>
                @endforeach
            </div>
            @error('clinicWorkingHours')<span class="form-error mt-3">{{ $message }}</span>@enderror
        </section>
        <section class="border-t border-[var(--line)] pt-6" x-data="{ showToken: false }">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div><p class="page-kicker">قناة خارجية اختيارية</p><h2 class="mt-1 font-black text-[var(--ink)]">إعدادات تيلغرام</h2><p class="mt-1 text-sm text-[var(--muted)]">يُحفظ رمز البوت مشفّرًا، ولا يؤثر تعطل تيلغرام في العمليات الأساسية.</p></div>
                <label class="flex items-center gap-3 rounded-2xl bg-[var(--surface-soft)] px-4 py-3"><input class="form-checkbox" type="checkbox" wire:model="telegramEnabled"><span class="font-bold text-[var(--ink-soft)]">تفعيل تيلغرام</span></label>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">رمز البوت</span><div class="relative"><input class="form-input pl-20" x-bind:type="showToken ? 'text' : 'password'" wire:model="telegramBotToken" autocomplete="new-password" placeholder="{{ $telegramTokenConfigured ? 'محفوظ — اتركه فارغًا للإبقاء عليه' : 'أدخل Bot Token' }}"><button class="absolute inset-y-1 left-1 rounded-xl px-3 text-xs font-bold text-[var(--brand-strong)]" type="button" x-on:click="showToken = ! showToken" x-text="showToken ? 'إخفاء' : 'إظهار'"></button></div>@if ($telegramTokenConfigured)<span class="mt-1 block text-xs text-[var(--muted)]">يوجد رمز مشفّر محفوظ حاليًا.</span>@endif @error('telegramBotToken')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">معرّف المحادثة الافتراضية</span><input class="form-input" dir="ltr" wire:model="telegramDefaultChatId" autocomplete="off">@error('telegramDefaultChatId')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
            <div class="mt-5 rounded-2xl border border-[var(--line)] p-4">
                <h3 class="font-black text-[var(--ink)]">مجموعات الإشعارات المرسلة إلى تيلغرام</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (['appointments' => 'المواعيد', 'sessions' => 'الجلسات', 'payments' => 'الدفعات', 'refunds' => 'الاستردادات', 'expenses' => 'المصروفات', 'settlements' => 'تسويات المعالجين', 'cashboxes' => 'الصناديق', 'weekly_closing' => 'الإغلاق الأسبوعي', 'patients' => 'المرضى الجدد'] as $group => $label)
                        <label class="flex items-center gap-2 rounded-xl bg-[var(--surface-soft)] px-3 py-2"><input class="form-checkbox" type="checkbox" wire:model="telegramNotifications.{{ $group }}"><span class="text-sm font-bold text-[var(--ink-soft)]">{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>
            @error('telegramTest')<p class="form-error mt-4">{{ $message }}</p>@enderror
            @error('telegramEnabled')<p class="form-error mt-4">{{ $message }}</p>@enderror
            <div class="mt-5 flex flex-wrap gap-3"><button class="btn btn-primary" type="button" wire:click="saveTelegram" wire:loading.attr="disabled">حفظ إعدادات تيلغرام</button><button class="btn btn-secondary" type="button" wire:click="testTelegram" wire:loading.attr="disabled">إرسال رسالة تجريبية</button></div>
        </section>
        <section class="border-t border-[var(--line)] pt-6" aria-labelledby="branding-heading">
            <div class="mb-5"><p class="page-kicker">الشعار وأيقونة التطبيق</p><h2 id="branding-heading" class="mt-1 font-black text-[var(--ink)]">الهوية البصرية</h2><p class="mt-1 text-sm text-[var(--muted)]">ملفات آمنة تظهر في تسجيل الدخول والقائمة الجانبية وعنوان المتصفح.</p></div>
            <div class="grid gap-5 md:grid-cols-2">
                <article class="rounded-[1.25rem] border border-[var(--line)] bg-[var(--surface-soft)] p-4">
                    <div class="mb-4 flex items-center gap-4">
                        <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface)] shadow-sm">
                            @if ($logo)<img src="{{ $logo->temporaryUrl() }}" alt="معاينة الشعار" class="size-full object-cover">@elseif ($logoPath)<img src="{{ Storage::disk('public')->url($logoPath) }}" alt="الشعار الحالي" class="size-full object-cover">@else<x-ui.brand compact />@endif
                        </div>
                        <div><h3 class="font-black text-[var(--ink)]">شعار العيادة</h3><p class="mt-1 text-xs leading-6 text-[var(--muted)]">PNG أو JPG أو WEBP، حتى 2MB.</p></div>
                    </div>
                    <label class="btn btn-secondary w-full cursor-pointer justify-center">
                        <span>{{ $logo ? 'استبدال الملف المحدد' : 'اختيار ملف الشعار' }}</span>
                        <input class="sr-only" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp">
                    </label>
                    @if ($logo)<p class="mt-2 truncate text-xs text-[var(--muted)]">الملف المحدد: {{ $logo->getClientOriginalName() }}</p>@endif
                    @error('logo')<span class="form-error">{{ $message }}</span>@enderror
                    @if ($logoPath)<button class="btn btn-ghost mt-3" type="button" wire:click="resetLogo">استعادة الشعار الافتراضي</button>@endif
                </article>
                <article class="rounded-[1.25rem] border border-[var(--line)] bg-[var(--surface-soft)] p-4">
                    <div class="mb-4 flex items-center gap-4">
                        <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface)] shadow-sm">
                            @if ($favicon && str_starts_with($favicon->getMimeType(), 'image/'))<img src="{{ $favicon->temporaryUrl() }}" alt="معاينة الأيقونة" class="size-12 object-contain">@elseif ($faviconPath)<img src="{{ Storage::disk('public')->url($faviconPath) }}" alt="الأيقونة الحالية" class="size-12 object-contain">@else<span class="text-3xl">✦</span>@endif
                        </div>
                        <div><h3 class="font-black text-[var(--ink)]">أيقونة التطبيق</h3><p class="mt-1 text-xs leading-6 text-[var(--muted)]">PNG أو ICO أو WEBP، حتى 512KB.</p></div>
                    </div>
                    <label class="btn btn-secondary w-full cursor-pointer justify-center">
                        <span>{{ $favicon ? 'استبدال الملف المحدد' : 'اختيار ملف الأيقونة' }}</span>
                        <input class="sr-only" type="file" wire:model="favicon" accept="image/png,image/x-icon,image/webp,.ico">
                    </label>
                    @if ($favicon)<p class="mt-2 truncate text-xs text-[var(--muted)]">الملف المحدد: {{ $favicon->getClientOriginalName() }}</p>@endif
                    @error('favicon')<span class="form-error">{{ $message }}</span>@enderror
                    @if ($faviconPath)<button class="btn btn-ghost mt-3" type="button" wire:click="resetFavicon">استعادة الأيقونة الافتراضية</button>@endif
                </article>
            </div>
            <button class="btn btn-secondary mt-5" type="button" wire:click="saveBranding" wire:loading.attr="disabled" wire:target="saveBranding,logo,favicon">حفظ الهوية البصرية</button>
        </section>
        <div class="flex justify-end border-t border-[var(--line)] pt-6"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">حفظ الإعدادات</button></div>
    </form>
</div>
