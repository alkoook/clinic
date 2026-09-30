<?php

use App\Models\Payment;
use App\Support\PresentationLabels;

it('presents financial audit actions entities fields and values in Arabic', function () {
    expect(PresentationLabels::auditAction('payment.created'))->toBe('تسجيل دفعة')
        ->and(PresentationLabels::entity(Payment::class))->toBe('دفعة جلسة علاجية')
        ->and(PresentationLabels::field('practitioner_share'))->toBe('حصة المعالج')
        ->and(PresentationLabels::field('idempotency_key'))->toBeNull()
        ->and(PresentationLabels::value('method', 'cash'))->toBe('نقدًا')
        ->and(PresentationLabels::value('status', 'paid'))->toBe('مدفوع')
        ->and(PresentationLabels::value('patient_source', 'clinic'))->toBe('من طرف العيادة')
        ->and(PresentationLabels::value('is_active', true))->toBe('نعم')
        ->and(PresentationLabels::value('description', 'Treatment session payment'))->toBe('دفعة جلسة علاجية');
});

it('does not expose unknown technical audit actions or entity classes', function () {
    expect(PresentationLabels::auditAction('internal.raw_action'))->toBe('عملية مالية موثقة')
        ->and(PresentationLabels::entity('App\\Models\\InternalRecord'))->toBe('سجل مالي');
});

it('contains no browser native confirmation attributes in application views', function () {
    $views = collect(File::allFiles(resource_path('views')))
        ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
        ->implode("\n");

    expect($views)->not->toContain('wire:confirm=')
        ->and($views)->not->toMatch('/\\b(?:window\\.)?(?:alert|prompt)\\s*\\(/');
});
