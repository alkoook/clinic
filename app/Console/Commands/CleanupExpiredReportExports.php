<?php

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('reports:cleanup')]
#[Description('حذف ملفات تقارير Excel المنتهية وتحديث حالتها')]
class CleanupExpiredReportExports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $removed = 0;

        ReportExport::query()
            ->where('status', 'completed')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($exports) use (&$removed): void {
                foreach ($exports as $export) {
                    if (filled($export->file_path) && str_starts_with($export->file_path, 'reports/')) {
                        Storage::disk('local')->delete($export->file_path);
                    }

                    $export->update(['status' => 'expired', 'file_path' => null]);
                    $removed++;
                }
            });

        $this->info("تم تنظيف {$removed} ملف تقرير منتهي.");

        return self::SUCCESS;
    }
}
