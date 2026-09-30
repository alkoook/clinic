<div class="space-y-5">
    <header class="page-header">
        <div>
            <p class="page-kicker">السجل الطبي الإداري</p>
            <h1>المرضى</h1>
            <p>بحث سريع بالاسم أو رقم الملف أو رقم الهاتف.</p>
        </div>
        @can('create', App\Models\Patient::class)
            <a class="btn btn-primary" href="{{ route('patients.create') }}" wire:navigate>إضافة مريض</a>
        @endcan
    </header>

    <section class="app-card grid gap-3 p-4 md:grid-cols-4">
        <label class="md:col-span-2">
            <span class="form-label">البحث</span>
            <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="الاسم، رقم الملف، الهاتف">
        </label>
        <label>
            <span class="form-label">المصدر</span>
            <select class="form-select" wire:model.live="source">
                <option value="">كل المصادر</option>
                @foreach ($this->sourceOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="form-label">الحالة</span>
            <select class="form-select" wire:model.live="active">
                <option value="">الكل</option>
                <option value="1">نشط</option>
                <option value="0">غير نشط</option>
            </select>
        </label>
    </section>

    <div class="table-shell">
        <table>
            <thead><tr><th>رقم الملف</th><th>المريض</th><th>الهاتف</th><th>المصدر</th><th>المعالج الحالي</th><th>النشاط</th><th></th></tr></thead>
            <tbody>
                @forelse ($this->patients as $patient)
                    <tr wire:key="patient-{{ $patient->id }}">
                        <td class="font-black text-[var(--brand-strong)]">{{ $patient->medical_file_number }}</td>
                        <td><p class="font-bold text-[var(--ink)]">{{ $patient->full_name }}</p><p class="text-xs text-[var(--muted)]">{{ $patient->companion_name ?: 'دون مرافق' }}</p></td>
                        <td dir="ltr" class="text-right">{{ $patient->phone ?: '—' }}</td>
                        <td>{{ $patient->source->label() }}</td>
                        <td>{{ $patient->currentPractitioner?->name ?: '—' }}</td>
                        <td><span class="status-badge {{ $patient->is_active ? 'status-success' : 'status-danger' }}">{{ $patient->is_active ? 'نشط' : 'متوقف' }}</span></td>
                        <td><a class="btn btn-secondary" href="{{ route('patients.show', $patient) }}" wire:navigate>فتح الملف</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state">لا توجد نتائج مطابقة.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->patients->links() }}
</div>
