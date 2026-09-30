<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Services\PatientAttachmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientAttachmentController extends Controller
{
    public function download(Patient $patient, PatientAttachment $patientAttachment): StreamedResponse
    {
        abort_unless($patientAttachment->patient_id === $patient->id, 404);
        Gate::authorize('download', $patientAttachment);
        abort_unless(Storage::disk($patientAttachment->disk)->exists($patientAttachment->path), 404);

        return Storage::disk($patientAttachment->disk)->download(
            $patientAttachment->path,
            $patientAttachment->original_name,
            [
                'Content-Type' => $patientAttachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cross-Origin-Resource-Policy' => 'same-origin',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }

    public function preview(Patient $patient, PatientAttachment $patientAttachment, PatientAttachmentService $attachments): BinaryFileResponse
    {
        abort_unless($patientAttachment->patient_id === $patient->id, 404);
        Gate::authorize('preview', $patientAttachment);
        abort_unless($attachments->canPreview($patientAttachment), 415, 'لا تتوفر معاينة لهذا النوع من الملفات.');
        abort_unless(Storage::disk($patientAttachment->disk)->exists($patientAttachment->path), 404);

        return response()->file(Storage::disk($patientAttachment->disk)->path($patientAttachment->path), [
            'Content-Type' => $patientAttachment->mime_type,
            'Content-Disposition' => 'inline; filename="preview.'.preg_replace('/[^a-z0-9]/i', '', $patientAttachment->extension).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'",
        ]);
    }
}
