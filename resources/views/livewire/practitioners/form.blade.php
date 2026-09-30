<div class="mx-auto max-w-4xl">
    <header class="page-header"><div><p class="page-kicker">فريق العيادة</p><h1>{{ $practitioner ? 'تعديل بيانات المعالج' : 'إضافة طبيب / معالج' }}</h1><p>ينشأ حساب دخول مرتبط تلقائياً بملف المعالج.</p></div><a class="btn btn-secondary" href="{{ $practitioner ? route('practitioners.show', $practitioner) : route('practitioners.index') }}" wire:navigate>رجوع</a></header>
    <form class="app-card space-y-6 p-5 sm:p-7" wire:submit="save">
        <div class="grid gap-4 md:grid-cols-2">
            <label><span class="form-label">الاسم الكامل *</span><input class="form-input" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">اسم المستخدم *</span><input class="form-input" dir="ltr" wire:model="username" autocomplete="off">@error('username')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">البريد الإلكتروني</span><input class="form-input" dir="ltr" type="email" wire:model="email">@error('email')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الهاتف</span><input class="form-input" dir="ltr" wire:model="phone">@error('phone')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">Telegram Chat ID (اختياري)</span><input class="form-input" dir="ltr" wire:model="telegramChatId" autocomplete="off"><span class="mt-1 block text-xs text-[var(--muted)]">يُستخدم لإشعارات هذا المعالج فقط.</span>@error('telegramChatId')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">كلمة المرور {{ $practitioner ? '(اتركها فارغة للإبقاء عليها)' : '*' }}</span><input class="form-input" dir="ltr" type="password" wire:model="password" autocomplete="new-password">@error('password')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">تأكيد كلمة المرور</span><input class="form-input" dir="ltr" type="password" wire:model="passwordConfirmation" autocomplete="new-password"></label>
        </div>
        <fieldset class="border-t border-[var(--line)] pt-6"><legend class="mb-3 font-black text-[var(--ink)]">الاختصاصات</legend><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach ($this->specialties as $specialty)<label class="flex items-center gap-3 rounded-2xl bg-[var(--surface-soft)] p-3"><input class="form-checkbox" type="checkbox" value="{{ $specialty->id }}" wire:model="specialtyIds"><span class="font-bold text-[var(--ink-soft)]">{{ $specialty->name }}</span></label>@endforeach</div>@error('specialtyIds')<span class="form-error">{{ $message }}</span>@enderror</fieldset>
        <label><span class="form-label">ملاحظات إدارية</span><textarea class="form-textarea" wire:model="administrativeNotes"></textarea>@error('administrativeNotes')<span class="form-error">{{ $message }}</span>@enderror</label>
        <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">الحساب والملف نشطان</span></label>
        <div class="flex justify-end gap-3 border-t border-[var(--line)] pt-6"><a class="btn btn-secondary" href="{{ route('practitioners.index') }}" wire:navigate>إلغاء</a><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">حفظ المعالج</button></div>
    </form>
</div>
