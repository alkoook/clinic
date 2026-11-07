<div class="space-y-6">

    {{-- Header --}}
    <header class="page-header">
        <div>
            <p class="page-kicker">
                ملف رقم {{ $patient->medical_file_number }}
            </p>

            <h1>{{ $patient->full_name }}</h1>

            <p>
                {{ $patient->phone ?: 'لا يوجد رقم هاتف' }}
                ·
                {{ $patient->source->label() }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('update', $patient)
                <a
                    class="btn btn-primary"
                    href="{{ route('patients.edit', $patient) }}"
                    wire:navigate
                >
                    تعديل الملف
                </a>
            @endcan

            <a
                class="btn btn-secondary"
                href="{{ route('patients.index') }}"
                wire:navigate
            >
                المرضى
            </a>
        </div>
    </header>


    {{-- Patient data --}}
    <section class="grid gap-4 lg:grid-cols-3">

        <article class="app-card p-5 lg:col-span-2">
            <h2 class="mb-4 font-black text-[var(--ink)]">
                بيانات المريض
            </h2>

            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'المرافق' => $patient->companion_name,
                    'مكان السكن' => $patient->residence_location,
                    'المستشفى المحوّل منه' => $patient->incoming_hospital_name,
                    'المعالج المحوّل' => $patient->referringPractitioner?->name,
                    'المعالج الحالي' => $patient->currentPractitioner?->name,
                    'الجهة المحوّل إليها' => $patient->outgoing_hospital_name,
                    'تاريخ التحويل الخارجي' => $patient->referred_out_at?->format('Y/m/d'),
                ] as $label => $value)

                    <div class="rounded-2xl bg-[var(--surface-soft)] p-4">
                        <dt class="text-xs font-bold text-[var(--muted)]">
                            {{ $label }}
                        </dt>

                        <dd class="mt-1 font-bold text-[var(--ink)]">
                            {{ $value ?: '—' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </article>


        <article class="app-card p-5">
            <h2 class="mb-4 font-black text-[var(--ink)]">
                ملاحظات
            </h2>

            <p class="whitespace-pre-line text-sm leading-7 text-[var(--ink-soft)]">
                {{ $patient->administrative_notes ?: 'لا توجد ملاحظات إدارية.' }}
            </p>

            @if ($patient->referral_notes)
                <hr class="my-4">

                <p class="whitespace-pre-line text-sm leading-7 text-[var(--ink-soft)]">
                    {{ $patient->referral_notes }}
                </p>
            @endif
        </article>

    </section>


    {{-- Appointments --}}
    <section class="app-card p-5 sm:p-6">
        <h2 class="mb-4 font-black text-[var(--ink)]">
            المواعيد
        </h2>

        <div class="table-shell shadow-none">
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المعالج</th>
                        <th>الغرفة</th>
                        <th>الخدمة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($this->appointments as $participant)
                        <tr>
                            <td>
                                {{ App\Support\ArabicFormat::dateTime($participant->appointment->starts_at) }}
                            </td>

                            <td>
                                {{ $participant->appointment->practitioner?->name }}
                            </td>

                            <td>
                                {{ $participant->appointment->room?->name }}
                            </td>

                            <td>
                                {{ $participant->appointment->treatmentType?->name }}
                            </td>

                            <td>
                                <span class="status-badge">
                                    {{ $participant->appointment->status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    لا توجد مواعيد مسجلة.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>


    {{-- Sessions --}}
    <section class="app-card p-5 sm:p-6">
        <h2 class="mb-4 font-black text-[var(--ink)]">
            الجلسات والمدفوعات
        </h2>

        <div class="table-shell shadow-none">
            <table>
                <thead>
                    <tr>
                        <th>الجلسة</th>
                        <th>التاريخ</th>
                        <th>الإجمالي</th>
                        <th>الخصم</th>
                        <th>حالة الدفع</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($this->sessions as $participant)
                        <tr>
                            <td>
                                <a
                                    class="font-bold text-[var(--brand-strong)]"
                                    href="{{ route('sessions.show', $participant->treatmentSession) }}"
                                    wire:navigate
                                >
                                    #{{ $participant->treatment_session_id }}
                                </a>
                            </td>

                            <td>
                                {{ $participant->treatmentSession->started_at->format('Y/m/d H:i') }}
                            </td>

                            <td>
                                {{ App\Support\ArabicFormat::money($participant->original_price) }}
                            </td>

                            <td>
                                {{ App\Support\ArabicFormat::money($participant->discount_amount) }}
                            </td>

                            <td>
                                <span class="status-badge">
                                    {{ $participant->payment_status->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    لم تُنشأ جلسات لهذا المريض.
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>


    {{-- Attachments --}}
    @can('viewAttachments', $patient)

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">

            {{-- Existing attachments --}}
            <div class="app-card min-w-0 p-5 sm:p-6">

                <h2 class="mb-4 font-black text-[var(--ink)]">
                    الملفات والمرفقات
                </h2>

                <div class="grid gap-2.5">

                    @forelse ($this->attachments as $attachment)

                        <article
                            class="rounded-xl border border-[var(--line)] p-3.5 transition hover:bg-[var(--surface-soft)]"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">

                                <div class="min-w-0 flex-1">

                                    <p class="truncate font-bold text-[var(--ink)]">
                                        {{ $attachment->title ?: $attachment->original_name }}
                                    </p>

                                    <p class="mt-0.5 truncate text-xs text-[var(--muted)]">
                                        {{ strtoupper($attachment->extension) }}
                                        ·
                                        {{ $attachment->size >= 1048576
                                            ? number_format($attachment->size / 1048576, 1).' م.ب'
                                            : number_format($attachment->size / 1024, 1).' ك.ب'
                                        }}
                                        ·
                                        {{ $attachment->uploaded_at->translatedFormat('l d/m/Y') }}
                                    </p>

                                    <p class="mt-0.5 text-xs text-[var(--muted)]">
                                        {{ $attachment->treatmentSession
                                            ? 'جلسة #'.$attachment->treatment_session_id.' — '.($attachment->treatmentSession->practitioner?->name ?: '—')
                                            : 'ملف عام للمريض'
                                        }}
                                        ·
                                        رفعه {{ $attachment->uploader?->name ?: 'النظام' }}
                                    </p>

                                </div>


                                <div class="flex flex-wrap gap-1.5 self-start">

                                    @can('preview', $attachment)

                                        @if (app(App\Services\PatientAttachmentService::class)->canPreview($attachment))

                                            <a
                                                class="btn btn-secondary btn-sm"
                                                target="_blank"
                                                rel="noopener"
                                                href="{{ route('patients.attachments.preview', [$patient, $attachment]) }}"
                                            >
                                                معاينة
                                            </a>

                                        @else

                                            <span class="self-center text-xs text-[var(--muted)]">
                                                لا تتوفر معاينة
                                            </span>

                                        @endif

                                    @endcan


                                    @can('download', $attachment)

                                        <a
                                            class="btn btn-secondary btn-sm"
                                            href="{{ route('patients.attachments.download', [$patient, $attachment]) }}"
                                        >
                                            تنزيل
                                        </a>

                                    @endcan

                                </div>

                            </div>
                        </article>

                    @empty

                        <div class="empty-state">
                            لا توجد مرفقات.
                        </div>

                    @endforelse

                </div>
            </div>


            {{-- Upload attachment --}}
            @can('uploadAttachment', $patient)

                <form
                    class="app-card space-y-5 p-5"
                    wire:submit.prevent="storeAttachments"
                >

                    <div>
                        <h2 class="font-black text-[var(--ink)]">
                            رفع مرفق
                        </h2>

                        <p class="mt-1 text-xs leading-6 text-[var(--muted)]">
                            العنوان والوصف والجلسة اختيارية. تُحفظ الملفات في التخزين الخاص فقط.
                        </p>
                    </div>


                    {{-- Upload errors --}}
                    @if ($errors->has('files') || $errors->has('files.*'))

                        <div class="flash-message flash-error" role="alert">
                            <p class="text-sm font-semibold">
                                {{ $errors->first('files') ?: $errors->first('files.*') }}
                            </p>
                        </div>

                    @endif


                    {{-- Title --}}
                    <label class="block">

                        <span class="form-label">
                            العنوان (اختياري)
                        </span>

                        <input
                            class="form-input"
                            wire:model="attachmentTitle"
                            placeholder="يُستخدم اسم الملف إن تُرك فارغًا"
                        >

                        @error('attachmentTitle')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </label>


                    {{-- Session --}}
                    <label class="block">

                        <span class="form-label">
                            ربط بجلسة (اختياري)
                        </span>

                        <select
                            class="form-select"
                            wire:model="treatmentSessionId"
                        >
                            <option value="">
                                دون ربط
                            </option>

                            @foreach ($this->sessions as $sessionParticipant)

                                <option value="{{ $sessionParticipant->treatment_session_id }}">
                                    جلسة #{{ $sessionParticipant->treatment_session_id }}
                                    —
                                    {{ \App\Support\ArabicFormat::appointmentDate(
                                        $sessionParticipant->treatmentSession->started_at
                                    ) }}
                                </option>

                            @endforeach
                        </select>

                        @error('treatmentSessionId')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </label>


                    {{-- Files --}}
                    <div>

                        <span class="form-label">
                            الملفات
                        </span>

                        <p class="mb-2 mt-1 text-xs text-[var(--muted)]">
                            يمكن رفع عدة ملفات — حتى 25 م.ب لكل ملف
                        </p>


                        {{-- Dropzone --}}
                        <div
                            x-data="{
                                isDragging: false,

                                handleDrop(event) {
                                    event.preventDefault();
                                    this.isDragging = false;

                                    const transfer = new DataTransfer();

                                    for (const file of event.dataTransfer.files) {
                                        transfer.items.add(file);
                                    }

                                    this.$refs.fileInput.files = transfer.files;

                                    this.$refs.fileInput.dispatchEvent(
                                        new Event('change', { bubbles: true })
                                    );
                                }
                            }"
                            @dragenter.prevent="isDragging = true"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)"
                            class="file-dropzone"
                            :class="isDragging ? 'file-dropzone-dragging' : ''"
                        >

                            <input
                                x-ref="fileInput"
                                type="file"
                                multiple
                                wire:model="files"
                                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip"
                                class="sr-only"
                            >


                            <div class="file-dropzone-content">

                                <svg
                                    class="size-7 text-[var(--brand)]"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                                    />
                                </svg>


                                <p class="text-sm font-bold text-[var(--ink)]">
                                    اسحب الملفات هنا
                                </p>


                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    @click.stop="$refs.fileInput.click()"
                                >
                                    اختيار الملفات
                                </button>

                            </div>

                        </div>


                        {{-- Uploading --}}
                        <span
                            class="mt-2 block text-xs text-[var(--muted)]"
                            wire:loading
                            wire:target="files"
                        >
                            جارٍ تجهيز الملفات للرفع…
                        </span>


                        {{-- Selected files --}}
                        @if ($files !== [])

                            <div class="mt-3 space-y-2">

                                @foreach ($files as $index => $pendingFile)

                                    <div
                                        class="flex items-center gap-2.5 rounded-xl border border-[var(--line)] bg-[var(--surface-soft)] px-3 py-2"
                                        wire:key="pending-file-{{ $pendingFile->getFilename() }}"
                                    >

                                        <div
                                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[var(--brand-soft)] text-[var(--brand-strong)]"
                                        >
                                            <svg
                                                class="size-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M9 14l3-3 3 3m-3-3V3"
                                                />
                                            </svg>
                                        </div>


                                        <div class="min-w-0 flex-1">

                                            <p class="truncate text-xs font-bold text-[var(--ink)]">
                                                {{ $pendingFile->getClientOriginalName() }}
                                            </p>

                                            <p class="text-xs text-[var(--muted)]">
                                                {{ strtoupper($pendingFile->getClientOriginalExtension()) }}
                                                ·
                                                {{ $pendingFile->getSize() >= 1048576
                                                    ? number_format($pendingFile->getSize() / 1048576, 1).' م.ب'
                                                    : number_format($pendingFile->getSize() / 1024, 1).' ك.ب'
                                                }}
                                            </p>

                                        </div>


                                        <button
                                            type="button"
                                            wire:click.stop="removeFile({{ $index }})"
                                            class="shrink-0 rounded-lg p-1 text-[var(--muted)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]"
                                            aria-label="إزالة الملف"
                                        >
                                            <svg
                                                class="size-4"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M6 18L18 6M6 6l12 12"
                                                />
                                            </svg>

                                            <span class="sr-only">
                                                إزالة الملف
                                            </span>
                                        </button>

                                    </div>

                                @endforeach

                            </div>

                        @endif


                        @error('files')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                        @error('files.*')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </div>


                    {{-- Description --}}
                    <label class="block">

                        <span class="form-label">
                            الوصف (اختياري)
                        </span>

                        <textarea
                            class="form-textarea"
                            wire:model="attachmentDescription"
                            placeholder="تفاصيل إضافية عن المرفق…"
                        ></textarea>

                        @error('attachmentDescription')
                            <span class="form-error">
                                {{ $message }}
                            </span>
                        @enderror

                    </label>


                    {{-- Submit --}}
                    <button
                        class="btn btn-primary w-full"
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="storeAttachments,files"
                    >

                        <span
                            wire:loading.remove
                            wire:target="storeAttachments,files"
                        >
                            رفع إلى التخزين الخاص
                        </span>

                        <span
                            wire:loading
                            wire:target="storeAttachments,files"
                        >
                            جارٍ الرفع والحفظ…
                        </span>

                    </button>

                </form>

            @endcan

        </section>

    @endcan

</div>
