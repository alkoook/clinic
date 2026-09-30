<div class="space-y-6">
    <header class="page-header">
        <div><p class="page-kicker">أثر مالي موثّق</p><h1>سجل العمليات المالية</h1><p>راجع العمليات بلغة واضحة، مع إبقاء السجل التقني الأصلي محفوظًا داخليًا دون عرضه للمستخدم.</p></div><button class="btn btn-primary" type="button" wire:click="requestExport" wire:loading.attr="disabled" wire:target="requestExport">تصدير Excel</button>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="ملخص السجل">
        @foreach (['total' => ['كل العمليات', 'info'], 'payments' => ['الدفعات', 'success'], 'refunds' => ['الاستردادات', 'danger'], 'expenses' => ['المصروفات', 'warning'], 'other' => ['عمليات أخرى', 'info']] as $key => [$label, $tone])
            <article class="metric-card audit-summary audit-tone-{{ $tone }}"><p>{{ $label }}</p><strong>{{ number_format($this->summary[$key]) }}</strong></article>
        @endforeach
    </section>

    <section class="app-card grid items-end gap-4 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
        <label><span class="form-label">نوع العملية</span><select class="form-select" wire:model.live="action"><option value="">كل العمليات</option>@foreach ($this->actionOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label><span class="form-label">المنفّذ</span><select class="form-select" wire:model.live="userId"><option value="">كل المستخدمين</option>@foreach ($this->users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
        <label><span class="form-label">نوع السجل</span><select class="form-select" wire:model.live="entityType"><option value="">كل السجلات</option>@foreach ($this->entityOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        <label><span class="form-label">المبلغ من</span><input class="form-input" type="number" min="0" wire:model.live.debounce.400ms="amountMin"></label>
        <label><span class="form-label">المبلغ إلى</span><input class="form-input" type="number" min="0" wire:model.live.debounce.400ms="amountMax"></label>
        <label><span class="form-label">من تاريخ</span><input class="form-input" type="date" wire:model.live="dateFrom"></label>
        <label><span class="form-label">إلى تاريخ</span><input class="form-input" type="date" wire:model.live="dateTo"></label>
    </section>

    <section class="grid gap-4" aria-label="العمليات المالية">
        @forelse ($this->logs as $log)
            @php($tone = App\Support\PresentationLabels::auditTone($log->action))
            <article class="app-card audit-entry audit-tone-{{ $tone }} relative overflow-hidden p-4 ps-7 sm:p-5 sm:ps-8" wire:key="financial-audit-{{ $log->id }}">
                <span class="audit-timeline-dot" aria-hidden="true"></span>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="status-badge audit-badge">{{ App\Support\PresentationLabels::auditAction($log->action) }}</span><time class="text-xs text-[var(--muted)]" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->translatedFormat('l، j F Y · h:i A') }}</time></div><h2 class="mt-3 font-bold text-[var(--ink)]">{{ App\Support\PresentationLabels::entityDescription($log) }}</h2><p class="mt-1 text-xs text-[var(--muted)]">نفّذها: {{ $log->user?->name ?: 'النظام' }} @if($log->ip_address) · عنوان الشبكة <span dir="ltr">{{ $log->ip_address }}</span>@endif</p></div>
                    @if (filled($log->before) || filled($log->after))
                        <details class="audit-details w-full sm:w-auto sm:min-w-72">
                            <summary class="btn btn-secondary cursor-pointer list-none">عرض التفاصيل</summary>
                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                @foreach (['before' => 'قبل العملية', 'after' => 'بعد العملية'] as $side => $title)
                                    @if (filled($log->{$side}))
                                        <section class="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4"><h3 class="mb-3 text-sm font-black text-[var(--ink)]">{{ $title }}</h3><dl class="grid gap-2">
                                            @foreach ($log->{$side} as $field => $value)
                                                @if ($label = App\Support\PresentationLabels::field($field))
                                                    <div class="grid grid-cols-[minmax(7rem,40%)_1fr] gap-3 border-b border-[var(--line-soft)] py-2 last:border-0"><dt class="text-xs font-bold text-[var(--muted)]">{{ $label }}</dt><dd class="text-sm font-semibold text-[var(--ink-soft)]">{{ is_array($value) ? 'تفاصيل محفوظة' : App\Support\PresentationLabels::value($field, $value) }}</dd></div>
                                                @endif
                                            @endforeach
                                        </dl></section>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                    @else
                        <span class="text-xs text-[var(--muted)]">لا توجد تفاصيل إضافية</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="empty-state">لا توجد عمليات مالية مطابقة ضمن الفترة المحددة.</div>
        @endforelse
    </section>
    {{ $this->logs->links() }}
</div>
