<div class="min-w-0 space-y-5">
    <header class="page-header">
        <div class="min-w-0">
            <p class="page-kicker">إدارة الوصول</p>
            <h1>المستخدمون</h1>
            <p>حسابات الإدارة والسكرتارية؛ حسابات المعالجين تُدار من ملفاتهم.</p>
        </div>
    </header>

    <div class="grid min-w-0 items-start gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <form class="app-card min-w-0 space-y-4 overflow-hidden p-4 sm:p-5" wire:submit="save">
            <h2 class="font-black text-[var(--ink)]">{{ $editingId ? 'تعديل المستخدم' : 'مستخدم جديد' }}</h2>

            <div class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-1">
                <label class="min-w-0"><span class="form-label">الاسم *</span><input class="form-input min-w-0" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="min-w-0"><span class="form-label">اسم المستخدم *</span><input class="form-input min-w-0" dir="ltr" wire:model="username" autocomplete="off">@error('username')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="min-w-0"><span class="form-label">البريد الإلكتروني</span><input class="form-input min-w-0" dir="ltr" type="email" wire:model="email">@error('email')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="min-w-0"><span class="form-label">الدور *</span><select class="form-select min-w-0" wire:model="roleId"><option value="">اختر الدور</option>@foreach ($this->roles as $role)<option value="{{ $role->id }}">{{ $role->label }}</option>@endforeach</select>@error('roleId')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="min-w-0"><span class="form-label">كلمة المرور {{ $editingId ? '(اختيارية)' : '*' }}</span><input class="form-input min-w-0" dir="ltr" type="password" wire:model="password" autocomplete="new-password">@error('password')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="min-w-0"><span class="form-label">تأكيد كلمة المرور</span><input class="form-input min-w-0" dir="ltr" type="password" wire:model="passwordConfirmation" autocomplete="new-password"></label>
            </div>

            <label class="flex min-w-0 items-center gap-3 rounded-2xl bg-[var(--surface-soft)] p-3"><input class="form-checkbox shrink-0" type="checkbox" wire:model="isActive"><span class="min-w-0 font-bold text-[var(--ink-soft)]">الحساب نشط</span></label>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1">
                <button class="btn btn-primary w-full" type="submit" wire:loading.attr="disabled" wire:target="save">حفظ</button>
                @if ($editingId)<button class="btn btn-secondary w-full" type="button" wire:click="cancelEditing">إلغاء</button>@endif
            </div>
        </form>

        <section class="min-w-0 space-y-4">
            <label class="block min-w-0"><span class="sr-only">البحث في المستخدمين</span><input class="form-input min-w-0" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث بالاسم أو اسم المستخدم"></label>
            @error('users')<div class="flash-message flash-error" role="alert">{{ $message }}</div>@enderror

            <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:hidden">
                @forelse ($this->users as $user)
                    <article class="app-card min-w-0 space-y-4 p-4" wire:key="user-card-{{ $user->id }}">
                        <div class="flex min-w-0 items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="break-words font-black text-[var(--ink)]">{{ $user->name }}</h3><p class="truncate text-left text-xs text-[var(--muted)]" dir="ltr" title="{{ $user->username }}">{{ $user->username }}</p></div>
                            <span class="shrink-0 text-xs font-bold text-[var(--muted)]">#{{ $user->id }}</span>
                        </div>
                        <dl class="grid min-w-0 grid-cols-2 gap-3 text-sm">
                            <div class="min-w-0 rounded-2xl bg-[var(--surface-soft)] p-3"><dt class="text-xs font-bold text-[var(--muted)]">الدور</dt><dd class="mt-1 break-words font-bold text-[var(--ink-soft)]">{{ $user->role?->label }}</dd></div>
                            <div class="min-w-0 rounded-2xl bg-[var(--surface-soft)] p-3"><dt class="text-xs font-bold text-[var(--muted)]">آخر دخول</dt><dd class="mt-1 break-words font-bold text-[var(--ink-soft)]">{{ $user->last_login_at?->format('Y/m/d H:i') ?: 'لم يدخل' }}</dd></div>
                        </dl>
                        <div class="grid min-w-0 grid-cols-1 gap-2 @unless ($user->practitioner) sm:grid-cols-2 @endunless">
                            <button class="status-badge min-h-11 w-full justify-center {{ $user->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $user->id }})" wire:loading.attr="disabled" wire:target="toggle({{ $user->id }})" @disabled($user->id === auth()->id())>{{ $user->is_active ? 'نشط' : 'متوقف' }}</button>
                            @unless ($user->practitioner)<button class="btn btn-secondary w-full" type="button" wire:click="edit({{ $user->id }})">تعديل</button>@endunless
                        </div>
                    </article>
                @empty
                    <div class="empty-state sm:col-span-2">لا يوجد مستخدمون.</div>
                @endforelse
            </div>

            <div class="table-shell hidden xl:block">
                <table><thead><tr><th>المستخدم</th><th>الدور</th><th>آخر دخول</th><th>الحالة</th><th></th></tr></thead><tbody>
                    @forelse ($this->users as $user)
                        <tr wire:key="user-row-{{ $user->id }}"><td><p class="font-bold text-[var(--ink)]">{{ $user->name }}</p><p class="text-xs text-[var(--muted)]" dir="ltr">{{ $user->username }}</p></td><td>{{ $user->role?->label }}</td><td>{{ $user->last_login_at?->format('Y/m/d H:i') ?: 'لم يدخل' }}</td><td><button class="status-badge {{ $user->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $user->id }})" wire:loading.attr="disabled" wire:target="toggle({{ $user->id }})" @disabled($user->id === auth()->id())>{{ $user->is_active ? 'نشط' : 'متوقف' }}</button></td><td>@unless ($user->practitioner)<button class="btn btn-secondary" type="button" wire:click="edit({{ $user->id }})">تعديل</button>@endunless</td></tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">لا يوجد مستخدمون.</div></td></tr>
                    @endforelse
                </tbody></table>
            </div>

            {{ $this->users->links() }}
        </section>
    </div>
</div>
