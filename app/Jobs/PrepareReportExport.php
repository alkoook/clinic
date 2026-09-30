<?php

namespace App\Jobs;

use App\Exports\ClinicReportExport;
use App\Models\ReportExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PrepareReportExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $reportExportId)
    {
        $this->onQueue('exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $reportExport = ReportExport::query()->with('requester:id,name')->findOrFail($this->reportExportId);

        if ($reportExport->status !== 'pending') {
            return;
        }

        $path = 'reports/'.$reportExport->getKey().'-'.str()->uuid().'.xlsx';
        $reportExport->update([
            'status' => 'processing',
            'file_path' => $path,
            'started_at' => now(),
            'error' => null,
        ]);

        $export = new ClinicReportExport(
            $reportExport->type,
            $reportExport->filters,
            includeClinicShare: $reportExport->requester->can('reports.view'),
            reportExportId: $reportExport->getKey(),
            generatedBy: $reportExport->requester->name,
        );

        $reportExport->update(['row_count' => (clone $export->query())->count()]);

        Excel::queue($export, $path, 'local')->chain([
            new CompleteReportExport($reportExport->getKey()),
        ])->allOnQueue('exports');
    }

    public function failed(Throwable $exception): void
    {
        ReportExport::query()->whereKey($this->reportExportId)->update([
            'status' => 'failed',
            'error' => 'تعذر بدء تجهيز التقرير. حاول مرة أخرى.',
            'completed_at' => now(),
        ]);
    }
}
