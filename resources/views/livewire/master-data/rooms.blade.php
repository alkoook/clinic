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
            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
                @forelse ($this->rooms as $room)
                    <article class="app-card flex min-w-0 flex-col gap-4 p-4" wire:key="room-{{ $room->id }}">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h2 class="truncate font-black text-[var(--ink)]">{{ $room->name }}</h2><p class="mt-1 text-xs font-bold text-[var(--brand-strong)]" dir="ltr">{{ $room->code ?: '—' }}</p></div>@can('master_data.manage')<button class="status-badge {{ $room->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $room->id }})">{{ $room->is_active ? 'متاحة' : 'متوقفة' }}</button>@else<span class="status-badge {{ $room->is_active ? 'status-success' : 'status-danger' }}">{{ $room->is_active ? 'متاحة' : 'متوقفة' }}</span>@endcan</div>
                        <p class="min-h-10 break-words text-sm leading-6 text-[var(--muted)]">{{ $room->description ?: 'لا يوجد وصف.' }}</p>
                        @can('master_data.manage')<div class="mt-auto grid grid-cols-2 gap-2"><button class="btn btn-secondary" type="button" wire:click="edit({{ $room->id }})">تعديل</button><button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف الغرفة؟', description: 'سيبقى تاريخ حجوزاتها محفوظًا في السجلات السابقة.', confirmLabel: 'حذف الغرفة', action: () => $wire.delete({{ $room->id }}) })">حذف</button></div>@endcan
                    </article>
                @empty<div class="empty-state sm:col-span-2 2xl:col-span-3">لا توجد غرف.</div>@endforelse
            </div>
            {{ $this->rooms->links() }}
        </section>
    </div>
</div>

