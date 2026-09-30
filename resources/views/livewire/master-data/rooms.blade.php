<div class="space-y-5">
    <header class="page-header"><div><p class="page-kicker">البيانات المرجعية</p><h1>الغرف</h1><p>إدارة غرف العيادة ومنع تعارض الحجوزات عليها.</p></div></header>
    <div class="grid gap-6 @can('master_data.manage') xl:grid-cols-[22rem_minmax(0,1fr)] @endcan">
        @can('master_data.manage')
        <form class="app-card space-y-4 p-5" wire:submit="save">
            <h2 class="font-black text-[var(--ink)]">{{ $editingId ? 'تعديل الغرفة' : 'غرفة جديدة' }}</h2>
            <label><span class="form-label">اسم الغرفة *</span><input class="form-input" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الرمز *</span><input class="form-input" dir="ltr" wire:model="code">@error('code')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الوصف</span><textarea class="form-textarea" wire:model="description"></textarea></label>
            <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">متاحة للحجز</span></label>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" type="submit">حفظ</button>@if ($editingId)<button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء</button>@endif</div>
        </form>
        @endcan
        <section class="space-y-4">
            <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث بالاسم أو الرمز">
            <div class="table-shell"><table><thead><tr><th>الغرفة</th><th>الرمز</th><th>الوصف</th><th>الحالة</th><th></th></tr></thead><tbody>
                @forelse ($this->rooms as $room)
                    <tr wire:key="room-{{ $room->id }}"><td class="font-bold text-[var(--ink)]">{{ $room->name }}</td><td dir="ltr" class="text-right">{{ $room->code }}</td><td>{{ $room->description ?: '—' }}</td><td>@can('master_data.manage')<button class="status-badge {{ $room->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $room->id }})">{{ $room->is_active ? 'متاحة' : 'متوقفة' }}</button>@else<span class="status-badge {{ $room->is_active ? 'status-success' : 'status-danger' }}">{{ $room->is_active ? 'متاحة' : 'متوقفة' }}</span>@endcan</td><td>@can('master_data.manage')<div class="flex flex-wrap gap-2"><button class="btn btn-secondary" type="button" wire:click="edit({{ $room->id }})">تعديل</button><button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف الغرفة؟', description: 'سيبقى تاريخ حجوزاتها محفوظًا في السجلات السابقة.', confirmLabel: 'حذف الغرفة', action: () => $wire.delete({{ $room->id }}) })">حذف</button></div>@else<span class="text-xs text-[var(--muted)]">عرض فقط</span>@endcan</td></tr>
                @empty<tr><td colspan="5"><div class="empty-state">لا توجد غرف.</div></td></tr>@endforelse
            </tbody></table></div>
            {{ $this->rooms->links() }}
        </section>
    </div>
</div>

