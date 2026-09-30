<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class PatientAttachmentService
{
    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip',
    ];

    /** @var list<string> */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'exe', 'com', 'msi', 'scr', 'dll', 'so', 'dylib',
        'bat', 'cmd', 'sh', 'bash', 'ps1', 'psm1', 'cgi',
        'js', 'jar', 'vbs', 'vb', 'wsf', 'wsh', 'hta', 'lnk', 'reg',
        'py', 'rb', 'pl', 'asp', 'aspx', 'jsp',
        'app', 'apk', 'deb', 'rpm', 'htaccess',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-cfb'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-cfb'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
        ],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function livewireRules(string $filesAttribute): array
    {
        return [
            $filesAttribute => ['required', 'array', 'min:1', 'max:10'],
            $filesAttribute.'.*' => ['required', 'file', 'max:25600', 'extensions:'.implode(',', self::ALLOWED_EXTENSIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function livewireMessages(string $filesAttribute): array
    {
        return [
            $filesAttribute.'.required' => 'اختر ملفًا واحدًا على الأقل للرفع.',
            $filesAttribute.'.min' => 'اختر ملفًا واحدًا على الأقل للرفع.',
            $filesAttribute.'.max' => 'يمكن رفع 10 ملفات كحد أقصى في المرة الواحدة.',
            $filesAttribute.'.*.required' => 'تعذر قراءة الملف. اختر ملفًا صالحًا ثم أعد المحاولة.',
            $filesAttribute.'.*.file' => 'يجب أن يكون المرفق ملفًا صالحًا.',
            $filesAttribute.'.*.max' => 'حجم الملف يجب ألا يتجاوز 25 م.ب.',
            $filesAttribute.'.*.extensions' => 'نوع الملف غير مسموح.',
        ];
    }

    /**
     * @return list<TemporaryUploadedFile|UploadedFile>
     */
    public static function readyUploads(mixed $uploads): array
    {
        $items = $uploads instanceof TemporaryUploadedFile || $uploads instanceof UploadedFile
            ? [$uploads]
            : (is_array($uploads) ? $uploads : []);

        return array_values(array_filter(
            $items,
            fn (mixed $file): bool => $file instanceof TemporaryUploadedFile || $file instanceof UploadedFile,
        ));
    }

    public function store(
        UploadedFile $file,
        Patient $patient,
        User $uploader,
        ?TreatmentSession $session = null,
        ?string $title = null,
        ?string $description = null,
    ): PatientAttachment {
        $this->assertSafe($file);
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = $this->storedName($patient, $session, $extension);
        $directory = 'patients/'.$patient->getKey().'/attachments';
        $path = $file->storeAs($directory, $storedName, 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['files' => 'تعذر رفع الملف. حاول مرة أخرى.']);
        }

        try {
            return PatientAttachment::query()->create([
                'patient_id' => $patient->getKey(),
                'treatment_session_id' => $session?->getKey(),
                'disk' => 'local',
                'path' => $path,
                'stored_name' => $storedName,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'extension' => $extension,
                'size' => $file->getSize(),
                'title' => $this->resolvedTitle($file, $title),
                'description' => filled($description) ? trim((string) $description) : null,
                'uploaded_by' => $uploader->getKey(),
                'uploaded_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function canPreview(PatientAttachment $attachment): bool
    {
        return str_starts_with($attachment->mime_type, 'image/') || $attachment->extension === 'pdf';
    }

    private function resolvedTitle(UploadedFile $file, ?string $title): string
    {
        if (filled($title)) {
            return Str::limit(trim((string) $title), 255, '');
        }

        return Str::limit(basename($file->getClientOriginalName()), 255, '');
    }

    private function assertSafe(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['files' => 'تعذر رفع الملف. تحقق من الملف وحاول مرة أخرى.']);
        }

        $originalName = strtolower($file->getClientOriginalName());
        $nameParts = array_values(array_filter(array_slice(explode('.', $originalName), 1)));

        if (array_intersect($nameParts, self::DANGEROUS_EXTENSIONS) !== []) {
            throw ValidationException::withMessages(['files' => 'نوع الملف غير مسموح لأسباب أمنية.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === '' || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['files' => 'نوع الملف غير مسموح.']);
        }

        $mimeType = $file->getMimeType();
        $allowedMimeTypes = self::ALLOWED_MIME_TYPES[$extension] ?? [];

        if (! is_string($mimeType) || ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'files' => 'محتوى الملف لا يطابق نوعه أو أن الملف غير آمن.',
            ]);
        }
    }

    private function storedName(Patient $patient, ?TreatmentSession $session, string $extension): string
    {
        $patientName = preg_replace('/[^\p{L}\p{N}]+/u', '-', trim($patient->full_name)) ?: 'مريض';
        $parts = [Str::limit($patientName, 80, '')];

        if ($session !== null) {
            $parts[] = 'جلسة-'.$session->getKey();
        }

        $parts[] = now()->format('Y-m-d');
        $parts[] = Str::lower(Str::random(6));

        return implode('_', $parts).'.'.$extension;
    }
}
