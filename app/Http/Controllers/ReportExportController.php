<?php

namespace App\Http\Controllers;

use App\Jobs\PrepareReportExport;
use App\Models\ReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportExportController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canViewAllReports = $user->can('reports.view');
        $canViewOwnReports = $user->can('reports.view_own');
        abort_unless($canViewAllReports || $canViewOwnReports, 403);
        $ownPractitionerId = null;

        if (! $canViewAllReports) {
            $ownPractitionerId = $user->practitioner?->getKey();
            abort_unless($canViewOwnReports && $ownPractitionerId !== null, 403);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['patients', 'appointments', 'sessions', 'financial', 'expenses', 'practitioners', 'cashbox', 'weekly_close', 'room_utilization', 'specialty_treatment', 'refunds_adjustments'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'practitioner_id' => ['nullable', 'integer', 'exists:practitioners,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'treatment_type_id' => ['nullable', 'integer', 'exists:treatment_types,id'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'source' => ['nullable', Rule::in(['clinic', 'practitioner'])],
            'status' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $canViewAllReports) {
            abort_unless(in_array($validated['type'], ['appointments', 'sessions', 'practitioners'], true), 403);
            $validated['practitioner_id'] = $ownPractitionerId;
        }

        $label = match ($validated['type']) {
            'appointments' => 'المواعيد',
            'sessions' => 'الجلسات',
            'financial' => 'المالية',
            'expenses' => 'المصروفات',
            'practitioners' => 'مستحقات_المعالجين',
            'cashbox' => 'حركات_الصندوق',
            'weekly_close' => 'الإغلاق_الأسبوعي',
            'room_utilization' => 'استخدام_الغرف',
            'specialty_treatment' => 'الاختصاصات_والعلاجات',
            'refunds_adjustments' => 'الاستردادات_والتعديلات',
            default => 'المرضى',
        };
        $reportExport = ReportExport::query()->create([
            'requested_by' => $user->getKey(),
            'type' => $validated['type'],
            'filters' => $validated,
            'status' => 'pending',
            'download_name' => 'تقرير_'.$label.'_'.now()->format('Y-m-d-His').'.xlsx',
        ]);
        PrepareReportExport::dispatch($reportExport->getKey())->onQueue('exports')->afterCommit();

        return redirect()->route('reports.index')->with('success', 'جارٍ تجهيز التقرير في الخلفية.');
    }
}
