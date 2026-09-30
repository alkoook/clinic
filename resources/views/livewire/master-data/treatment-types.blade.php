<div class="space-y-5">
    <header class="page-header"><div><p class="page-kicker">البيانات المرجعية</p><h1>أنواع العلاج والجلسات</h1><p>تعريف الخدمات الفردية والجماعية وربطها بالاختصاص.</p></div></header>
    <div class="grid gap-6 xl:grid-cols-[23rem_minmax(0,1fr)]">
        <form class="app-card space-y-4 p-5" wire:submit="save">
            <h2 class="font-black text-[var(--ink)]">{{ $editingId ? 'تعديل النوع' : 'نوع علاج جديد' }}</h2>
            <label><span class="form-label">الاختصاص</span><select class="form-select" wire:model="specialtyId"><option value="">عام</option>@foreach ($this->specialties as $specialty)<option value="{{ $specialty->id }}">{{ $specialty->name }}</option>@endforeach</select></label>
            <label><span class="form-label">الاسم *</span><input class="form-input" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الوصف</span><textarea class="form-textarea" wire:model="description"></textarea></label>
            <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isGroup"><span class="font-bold text-[var(--ink-soft)]">يسمح بجلسة جماعية</span></label>
            <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">نشط</span></label>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" type="submit">حفظ</button>@if ($editingId)<button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء</button>@endif</div>
        </form>
        <section class="space-y-4">
            <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث باسم العلاج">
            <div class="table-shell"><table><thead><tr><th>النوع</th><th>الاختصاص</th><th>النمط</th><th>الحالة</th><th></th></tr></thead><tbody>
                @forelse ($this->treatmentTypes as $type)
                    <tr wire:key="type-{{ $type->id }}"><td><p class="font-bold text-[var(--ink)]">{{ $type->name }}</p><p class="text-xs text-[var(--muted)]">{{ $type->description }}</p></td><td>{{ $type->specialty?->name ?: 'عام' }}</td><td><span class="status-badge">{{ $type->is_group ? 'جماعي' : 'فردي' }}</span></td><td><button class="status-badge {{ $type->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $type->id }})">{{ $type->is_active ? 'نشط' : 'متوقف' }}</button></td><td><div class="flex flex-wrap gap-2"><button class="btn btn-secondary" type="button" wire:click="edit({{ $type->id }})">تعديل</button><button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف نوع العلاج؟', description: 'سيبقى تاريخه محفوظًا في الجلسات السابقة.', confirmLabel: 'حذف نوع العلاج', action: () => $wire.delete({{ $type->id }}) })">حذف</button></div></td></tr>
                @empty<tr><td colspan="5"><div class="empty-state">لا توجد أنواع علاج.</div></td></tr>@endforelse
            </tbody></table></div>
            {{ $this->treatmentTypes->links() }}
        </section>
    </div>
</div>

