<div>
    <div class="mb-7 sm:mb-8">
        <p class="page-kicker">بوابة الموظفين</p>
        <h1 class="mt-2 text-[1.45rem] font-bold tracking-tight text-[var(--ink)] sm:text-[1.8rem]">أهلًا بعودتك</h1>
        <p class="mt-2 text-xs leading-6 text-[var(--muted)] sm:text-sm sm:leading-7">أدخل بيانات حسابك للوصول إلى مساحة إدارة العيادة.</p>
    </div>

    <form wire:submit="authenticate" class="flex flex-col gap-5" novalidate>
        <div>
            <label for="username" class="form-label">اسم المستخدم</label>
            <input
                id="username"
                type="text"
                wire:model="username"
                class="form-input h-12"
                dir="ltr"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                inputmode="text"
                maxlength="255"
                placeholder="ahmad"
                autofocus
                @error('username') aria-invalid="true" @enderror
                required
            >
            @error('username')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">كلمة المرور</label>
            <div
                data-login-password-control
                class="flex h-12 overflow-hidden rounded-2xl border border-[var(--app-input-border)] bg-[var(--app-input-bg)] transition duration-200 focus-within:border-[var(--focus)] focus-within:ring-4 focus-within:ring-[var(--focus-ring)]"
                dir="rtl"
                x-data="{ visible: false }"
            >
                <input
                    id="password"
                    x-bind:type="visible ? 'text' : 'password'"
                    wire:model="password"
                    class="min-w-0 flex-1 border-0 bg-transparent px-4 text-right text-sm font-medium text-[var(--ink)] outline-none placeholder:text-[var(--muted)]/65 focus:ring-0"
                    dir="ltr"
                    autocomplete="current-password"
                    maxlength="1024"
                    placeholder="••••••••"
                    @error('password') aria-invalid="true" @enderror
                    required
                >
                <button
                    type="button"
                    class="grid w-11 shrink-0 place-items-center bg-transparent p-0 text-[var(--muted)] transition-colors hover:text-[var(--ink)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--focus)]"
                    x-on:click="visible = ! visible"
                    x-bind:aria-label="visible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                    x-bind:aria-pressed="visible"
                >
                    <x-ui.icon x-show="! visible" name="eye" class="size-5" />
                    <x-ui.icon x-cloak x-show="visible" name="eye-off" class="size-5" />
                </button>
            </div>
            @error('password')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <label class="inline-flex w-fit cursor-pointer items-center gap-2.5 text-xs font-semibold text-[var(--ink-soft)] sm:text-sm">
            <input type="checkbox" wire:model="remember" class="form-checkbox">
            <span>تذكرني على هذا الجهاز</span>
        </label>

        <button type="submit" class="btn btn-primary mt-1 min-h-12 w-full" wire:loading.attr="disabled" wire:target="authenticate">
            <span wire:loading.remove wire:target="authenticate">تسجيل الدخول</span>
            <span wire:loading.flex wire:target="authenticate" class="items-center gap-2">
                <span class="size-4 animate-spin rounded-full border-2 border-white/35 border-t-white motion-reduce:animate-none"></span>
                جارٍ التحقق…
            </span>
        </button>
    </form>

    <div class="mt-6 flex items-start gap-2.5 rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] px-4 py-3 text-[0.7rem] leading-6 text-[var(--muted)] sm:text-xs">
        <x-ui.icon name="lock" class="mt-1 size-4 shrink-0 text-[var(--brand-strong)]" />
        <p>لا تشارك بيانات حسابك، وسجّل الخروج عند استخدام جهاز مشترك.</p>
    </div>
</div>
