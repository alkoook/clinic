<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ReportExport $reportExport): StreamedResponse
    {
        abort_unless(
            $reportExport->requested_by === auth()->id() || auth()->user()->can('reports.view'),
            403,
        );
        abort_unless(
            $reportExport->status === 'completed'
            && $reportExport->expires_at?->isFuture()
            && filled($reportExport->file_path)
            && Storage::disk('local')->exists($reportExport->file_path),
            404,
        );

        return Storage::disk('local')->download($reportExport->file_path, $reportExport->download_name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
