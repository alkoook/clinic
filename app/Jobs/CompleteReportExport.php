<?php

namespace App\Jobs;

use App\Enums\ClinicNotificationType;
use App\Models\ReportExport;
use App\Notifications\ClinicEventNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompleteReportExport implements ShouldQueue
{
    use Queueable;

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
        $reportExport = ReportExport::query()->with('requester')->findOrFail($this->reportExportId);
        $reportExport->update([
            'status' => 'completed',
            'completed_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);

        $reportExport->requester->notify(new ClinicEventNotification(
            ClinicNotificationType::ReportExportReady,
            'تقريرك أصبح جاهزًا',
            $reportExport->download_name.' أصبح جاهزًا للتنزيل لمدة 48 ساعة.',
            route('reports.exports.download', $reportExport),
            ['report_export_id' => $reportExport->getKey()],
        ));
    }
}
