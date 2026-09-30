<div class="space-y-5">
    <header class="page-header"><div><p class="page-kicker">البيانات المرجعية</p><h1>الاختصاصات</h1><p>إدارة اختصاصات الأطباء والمعالجين.</p></div></header>
    <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <form class="app-card space-y-4 p-5" wire:submit="save">
            <h2 class="font-black text-[var(--ink)]">{{ $editingId ? 'تعديل الاختصاص' : 'اختصاص جديد' }}</h2>
            <label><span class="form-label">الاسم *</span><input class="form-input" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الوصف</span><textarea class="form-textarea" wire:model="description"></textarea>@error('description')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">نشط</span></label>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" type="submit">حفظ</button>@if ($editingId)<button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء</button>@endif</div>
        </form>
        <section class="space-y-4">
            <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث باسم الاختصاص">
            <div class="table-shell"><table><thead><tr><th>الاختصاص</th><th>الوصف</th><th>الحالة</th><th></th></tr></thead><tbody>
                @forelse ($this->specialties as $specialty)
                    <tr wire:key="specialty-{{ $specialty->id }}"><td class="font-bold text-[var(--ink)]">{{ $specialty->name }}</td><td>{{ $specialty->description ?: '—' }}</td><td><button class="status-badge {{ $specialty->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $specialty->id }})">{{ $specialty->is_active ? 'نشط' : 'متوقف' }}</button></td><td><div class="flex flex-wrap gap-2"><button class="btn btn-secondary" type="button" wire:click="edit({{ $specialty->id }})">تعديل</button><button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف الاختصاص؟', description: 'سيبقى تاريخه محفوظًا في السجلات السابقة.', confirmLabel: 'حذف الاختصاص', action: () => $wire.delete({{ $specialty->id }}) })">حذف</button></div></td></tr>
                @empty<tr><td colspan="4"><div class="empty-state">لا توجد اختصاصات.</div></td></tr>@endforelse
            </tbody></table></div>
            {{ $this->specialties->links() }}
        </section>
    </div>
</div>

