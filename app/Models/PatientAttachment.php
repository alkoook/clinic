<?php

namespace App\Models;

use Database\Factories\PatientAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'patient_id',
    'treatment_session_id',
    'disk',
    'path',
    'stored_name',
    'original_name',
    'mime_type',
    'extension',
    'size',
    'title',
    'description',
    'uploaded_by',
    'uploaded_at',
])]
class PatientAttachment extends Model
{
    /** @use HasFactory<PatientAttachmentFactory> */
    use HasFactory, SoftDeletes;

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }
}
