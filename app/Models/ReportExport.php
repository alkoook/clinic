<?php

namespace App\Models;

use Database\Factories\ReportExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['requested_by', 'type', 'filters', 'status', 'file_path', 'download_name', 'row_count', 'error', 'started_at', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
