<div class="mx-auto max-w-5xl">
    <header class="page-header">
        <div>
            <p class="page-kicker">{{ $patient ? 'تحديث البيانات' : 'فتح ملف جديد' }}</p>
            <h1>{{ $patient ? 'تعديل ملف المريض' : 'إضافة مريض' }}</h1>
            <p>اختر المعالج أولًا ليُقترح رقم الملف تلقائيًا ضمن ملفاته.</p>
        </div>
        <a class="btn btn-secondary" href="{{ $patient ? route('patients.show', $patient) : route('patients.index') }}" wire:navigate>رجوع</a>
    </header>

    <form class="app-card space-y-7 p-5 sm:p-7" wire:submit="save">
        <section>
            <h2 class="mb-4 font-black text-[var(--ink)]">البيانات الأساسية</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">الدكتور / المعالج *</span><select class="form-select" wire:model.live="currentPractitionerId"><option value="">اختر المعالج أولًا</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select>@error('currentPractitionerId')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">رقم الملف *</span><input class="form-input" type="number" min="1" inputmode="numeric" wire:model="medicalFileNumber" autocomplete="off" @disabled(! $currentPractitionerId)><span class="mt-1 block text-xs text-[var(--muted)]">يُقترح تلقائيًا بعد اختيار المعالج، ويمكن تعديله إلى رقم غير مكرر لديه.</span>@error('medicalFileNumber')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="md:col-span-2"><span class="form-label">اسم المريض *</span><input class="form-input" wire:model="fullName">@error('fullName')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">العمر *</span><input class="form-input" type="number" min="0" max="130" inputmode="numeric" wire:model="age">@error('age')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">الجنس *</span><select class="form-select" wire:model="gender"><option value="">اختر</option><option value="male">ذكر</option><option value="female">أنثى</option></select>@error('gender')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">الحالة الاجتماعية *</span><select class="form-select" wire:model="maritalStatus"><option value="">اختر</option><option value="single">أعزب / عزباء</option><option value="married">متزوج / متزوجة</option><option value="divorced">مطلق / مطلقة</option><option value="widowed">أرمل / أرملة</option></select>@error('maritalStatus')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">الحالة الوظيفية / المهنة</span><input class="form-input" wire:model="occupation">@error('occupation')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">الشخص المرافق</span><input class="form-input" wire:model="companionName">@error('companionName')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">رقم الهاتف</span><input class="form-input" dir="ltr" wire:model="phone">@error('phone')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">مكان السكن</span><input class="form-input" wire:model="residenceLocation" placeholder="المدينة أو الحي">@error('residenceLocation')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">المستشفى المحوّل منه</span><input class="form-input" wire:model="incomingHospitalName">@error('incomingHospitalName')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
        </section>

        <section class="border-t border-[var(--line)] pt-6">
            <h2 class="mb-4 font-black text-[var(--ink)]">المصدر والإحالات</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">مصدر المريض *</span><select class="form-select" wire:model.live="source" @disabled($patient)>@foreach (App\Enums\PatientSource::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>@if ($patient)<span class="mt-1 block text-xs text-[var(--muted)]">يُثبّت المصدر الأصلي لحماية الاستحقاقات المالية التاريخية.</span>@endif @error('source')<span class="form-error">{{ $message }}</span>@enderror</label>
                @if ($source === App\Enums\PatientSource::Practitioner->value)
                    <label><span class="form-label">الطبيب / المعالج المحوّل *</span><select class="form-select" wire:model="referringPractitionerId" @disabled($patient)><option value="">اختر المعالج</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select>@error('referringPractitionerId')<span class="form-error">{{ $message }}</span>@enderror</label>
                @endif
                <label class="flex items-center gap-3 rounded-2xl bg-[var(--surface-soft)] p-4 md:col-span-2"><input class="form-checkbox" type="checkbox" wire:model.live="wasReferredOut"><span class="font-bold text-[var(--ink-soft)]">تم تحويل المريض إلى جهة خارجية</span></label>
                @if ($wasReferredOut)
                    <label><span class="form-label">الجهة المحوّل إليها *</span><input class="form-input" wire:model="outgoingHospitalName">@error('outgoingHospitalName')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label><span class="form-label">تاريخ التحويل</span><input class="form-input" type="date" wire:model="referredOutAt">@error('referredOutAt')<span class="form-error">{{ $message }}</span>@enderror</label>
                @endif
                <label class="md:col-span-2"><span class="form-label">ملاحظات الإحالة</span><textarea class="form-textarea" wire:model="referralNotes"></textarea>@error('referralNotes')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
        </section>

        <section class="border-t border-[var(--line)] pt-6">
            <label><span class="form-label">ملاحظات إدارية</span><textarea class="form-textarea" wire:model="administrativeNotes"></textarea>@error('administrativeNotes')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label class="mt-4 flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">الملف نشط</span></label>
        </section>

        <div class="flex flex-wrap justify-end gap-3 border-t border-[var(--line)] pt-6">
            <a class="btn btn-secondary" href="{{ $patient ? route('patients.show', $patient) : route('patients.index') }}" wire:navigate>إلغاء</a>
            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">حفظ الملف</button>
        </div>
    </form>
</div>
