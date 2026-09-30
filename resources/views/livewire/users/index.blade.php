<div class="space-y-5">
    <header class="page-header"><div><p class="page-kicker">إدارة الوصول</p><h1>المستخدمون</h1><p>حسابات الإدارة والسكرتارية؛ حسابات المعالجين تُدار من ملفاتهم.</p></div></header>
    <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
        <form class="app-card space-y-4 p-5" wire:submit="save">
            <h2 class="font-black text-[var(--ink)]">{{ $editingId ? 'تعديل المستخدم' : 'مستخدم جديد' }}</h2>
            <label><span class="form-label">الاسم *</span><input class="form-input" wire:model="name">@error('name')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">اسم المستخدم *</span><input class="form-input" dir="ltr" wire:model="username" autocomplete="off">@error('username')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">البريد الإلكتروني</span><input class="form-input" dir="ltr" type="email" wire:model="email">@error('email')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الدور *</span><select class="form-select" wire:model="roleId"><option value="">اختر الدور</option>@foreach ($this->roles as $role)<option value="{{ $role->id }}">{{ $role->label }}</option>@endforeach</select>@error('roleId')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">كلمة المرور {{ $editingId ? '(اختيارية)' : '*' }}</span><input class="form-input" dir="ltr" type="password" wire:model="password" autocomplete="new-password">@error('password')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">تأكيد كلمة المرور</span><input class="form-input" dir="ltr" type="password" wire:model="passwordConfirmation" autocomplete="new-password"></label>
            <label class="flex items-center gap-3"><input class="form-checkbox" type="checkbox" wire:model="isActive"><span class="font-bold text-[var(--ink-soft)]">الحساب نشط</span></label>
            <div class="flex gap-2"><button class="btn btn-primary flex-1" type="submit">حفظ</button>@if ($editingId)<button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء</button>@endif</div>
        </form>
        <section class="space-y-4">
            <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث بالاسم أو اسم المستخدم">
            <div class="table-shell"><table><thead><tr><th>المستخدم</th><th>الدور</th><th>آخر دخول</th><th>الحالة</th><th></th></tr></thead><tbody>
                @forelse ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}"><td><p class="font-bold text-[var(--ink)]">{{ $user->name }}</p><p class="text-xs text-[var(--muted)]" dir="ltr">{{ $user->username }}</p></td><td>{{ $user->role?->label }}</td><td>{{ $user->last_login_at?->format('Y/m/d H:i') ?: 'لم يدخل' }}</td><td><button class="status-badge {{ $user->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $user->id }})" @disabled($user->id === auth()->id())>{{ $user->is_active ? 'نشط' : 'متوقف' }}</button></td><td>@unless ($user->practitioner)<button class="btn btn-secondary" type="button" wire:click="edit({{ $user->id }})">تعديل</button>@endunless</td></tr>
                @empty<tr><td colspan="5"><div class="empty-state">لا يوجد مستخدمون.</div></td></tr>@endforelse
            </tbody></table></div>
            {{ $this->users->links() }}
        </section>
    </div>
</div>
