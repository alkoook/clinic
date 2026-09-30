<div
    x-data="{ open: false, loading: false, title: '', description: '', confirmLabel: '', tone: 'danger', action: null, show(detail) { this.title = detail.title; this.description = detail.description; this.confirmLabel = detail.confirmLabel || 'تأكيد'; this.tone = detail.tone || 'danger'; this.action = detail.action; this.open = true; this.$nextTick(() => this.$refs.cancel.focus()); }, async confirm() { if (this.loading || !this.action) return; this.loading = true; try { await this.action(); this.open = false; } finally { this.loading = false; } } }"
    x-on:confirm-action.window="show($event.detail)"
    x-on:keydown.escape.window="if (open && !loading) open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[100] grid place-items-center p-4"
    role="dialog"
    aria-modal="true"
    x-bind:aria-label="title"
>
    <button class="absolute inset-0 bg-slate-950/55 backdrop-blur-sm" type="button" aria-label="إغلاق نافذة التأكيد" x-on:click="if (!loading) open = false" x-transition.opacity></button>
    <section class="confirm-dialog relative w-full max-w-md overflow-hidden rounded-[1.5rem] border border-[var(--line)] bg-[var(--surface-elevated)] p-6 shadow-[var(--app-shadow-floating)]" x-show="open" x-transition:enter="motion-safe:transition motion-safe:duration-300 motion-safe:ease-out" x-transition:enter-start="translate-y-4 scale-95 opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100" x-transition:leave="motion-safe:transition motion-safe:duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-2 scale-95 opacity-0">
        <div class="mb-5 grid size-12 place-items-center rounded-2xl bg-[var(--danger-soft)] text-xl text-[var(--danger)]">!</div>
        <h2 class="text-xl font-black text-[var(--ink)]" x-text="title"></h2>
        <p class="mt-2 text-sm leading-7 text-[var(--muted)]" x-text="description"></p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button x-ref="cancel" class="btn btn-secondary" type="button" x-on:click="open = false" x-bind:disabled="loading">تراجع</button>
            <button class="btn" x-bind:class="tone === 'primary' ? 'btn-primary' : 'btn-danger'" type="button" x-on:click="confirm()" x-bind:disabled="loading">
                <span x-show="!loading" x-text="confirmLabel"></span>
                <span x-show="loading" class="items-center gap-2"><span class="inline-block size-4 animate-spin rounded-full border-2 border-current/30 border-t-current motion-reduce:animate-none"></span> جارٍ التنفيذ…</span>
            </button>
        </div>
    </section>
</div>
