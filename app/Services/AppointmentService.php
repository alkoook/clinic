<?php

namespace App\Services;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\ClinicNotificationType;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Models\TreatmentType;
use App\Models\User;
use App\Support\ArabicFormat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class AppointmentService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ClinicWorkingHoursService $clinicWorkingHoursService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *     practitioner_id: int,
     *     room_id: int,
     *     treatment_type_id: int,
     *     starts_at: CarbonInterface|DateTimeInterface|string,
     *     duration_minutes: int,
     *     kind: AppointmentKind|string,
     *     patient_ids: list<int>,
     *     notes?: string|null
     * }  $data
     */
    public function book(array $data, User $actor): Appointment
    {
        if (! $actor->hasPermission('appointments.manage')) {
            throw SchedulingException::permissionDenied('appointments.manage');
        }

        $appointment = DB::transaction(
            fn (): Appointment => $this->bookWithinTransaction($data, $actor),
            3,
        );

        $this->notifyAppointment($appointment, ClinicNotificationType::AppointmentCreated, '📅 موعد جديد');

        return $appointment;
    }

    /**
     * Missing values inherit from the original appointment.
     *
     * @param  array{
     *     practitioner_id?: int,
     *     room_id?: int,
     *     treatment_type_id?: int,
     *     starts_at?: CarbonInterface|DateTimeInterface|string,
     *     duration_minutes?: int,
     *     kind?: AppointmentKind|string,
     *     patient_ids?: list<int>,
     *     notes?: string|null
     * }  $data
     */
    public function reschedule(Appointment $appointment, array $data, User $actor): Appointment
    {
        if (! $actor->hasPermission('appointments.manage')) {
            throw SchedulingException::permissionDenied('appointments.manage');
        }

        $replacement = DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($this->status($lockedAppointment), [AppointmentStatus::Scheduled, AppointmentStatus::Attended], true)) {
                throw SchedulingException::invalidState('rescheduled');
            }

            if (TreatmentSession::query()->where('appointment_id', $lockedAppointment->getKey())->exists()) {
                throw SchedulingException::invalidState('rescheduled after a treatment session was created');
            }

            $patientIds = AppointmentParticipant::query()
                ->where('appointment_id', $lockedAppointment->getKey())
                ->orderBy('id')
                ->pluck('patient_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $replacementData = array_merge([
                'practitioner_id' => (int) $lockedAppointment->getAttribute('practitioner_id'),
                'room_id' => (int) $lockedAppointment->getAttribute('room_id'),
                'treatment_type_id' => (int) $lockedAppointment->getAttribute('treatment_type_id'),
                'starts_at' => $lockedAppointment->getAttribute('starts_at'),
                'duration_minutes' => (int) $lockedAppointment->getAttribute('duration_minutes'),
                'kind' => $this->kind($lockedAppointment),
                'patient_ids' => $patientIds,
                'notes' => $lockedAppointment->getAttribute('notes'),
            ], $data);

            $replacement = $this->bookWithinTransaction(
                $replacementData,
                $actor,
                (int) $lockedAppointment->getKey(),
                (int) $lockedAppointment->getKey(),
            );

            $lockedAppointment->forceFill([
                'status' => AppointmentStatus::Rescheduled->value,
                'updated_by' => $actor->getKey(),
            ]);
            $lockedAppointment->save();

            return $replacement;
        }, 3);

        $this->notifyAppointment($replacement, ClinicNotificationType::AppointmentRescheduled, '🔄 تمت إعادة جدولة موعد');

        return $replacement;
    }

    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        if (! $actor->hasPermission('appointments.manage')) {
            throw SchedulingException::permissionDenied('appointments.manage');
        }

        $wasCancelled = false;
        $cancelled = DB::transaction(function () use ($appointment, $reason, $actor, &$wasCancelled): Appointment {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $status = $this->status($lockedAppointment);

            if ($status === AppointmentStatus::Cancelled) {
                return $lockedAppointment;
            }

            if (! in_array($status, [AppointmentStatus::Scheduled, AppointmentStatus::Attended], true)) {
                throw SchedulingException::invalidState('cancelled');
            }

            if (TreatmentSession::query()->where('appointment_id', $lockedAppointment->getKey())->exists()) {
                throw SchedulingException::invalidState('cancelled after a treatment session was created');
            }

            if (trim($reason) === '') {
                throw SchedulingException::invalidState('cancelled without a reason');
            }

            $lockedAppointment->forceFill([
                'status' => AppointmentStatus::Cancelled->value,
                'cancellation_reason' => trim($reason),
                'cancelled_at' => now(),
                'updated_by' => $actor->getKey(),
            ]);
            $lockedAppointment->save();
            $wasCancelled = true;

            return $lockedAppointment->refresh();
        }, 3);

        if ($wasCancelled) {
            $this->notifyAppointment($cancelled, ClinicNotificationType::AppointmentCancelled, '❌ تم إلغاء موعد');
        }

        return $cancelled;
    }

    /**
     * @param  array{
     *     practitioner_id: int,
     *     room_id: int,
     *     treatment_type_id: int,
     *     starts_at: CarbonInterface|DateTimeInterface|string,
     *     duration_minutes: int,
     *     kind: AppointmentKind|string,
     *     patient_ids: list<int>,
     *     notes?: string|null
     * }  $data
     */
    private function bookWithinTransaction(
        array $data,
        User $actor,
        ?int $excludeAppointmentId = null,
        ?int $rescheduledFromId = null,
    ): Appointment {
        $startsAt = $this->dateTime($data['starts_at']);
        $durationMinutes = $this->validatedDurationMinutes($data['duration_minutes'] ?? null);

        if ($startsAt->second !== 0) {
            throw SchedulingException::invalidInterval();
        }

        $endsAt = $startsAt->addMinutes($durationMinutes);

        if (! $startsAt->isSameDay($endsAt)) {
            throw SchedulingException::invalidInterval();
        }

        $kind = $data['kind'] instanceof AppointmentKind
            ? $data['kind']
            : AppointmentKind::from((string) $data['kind']);
        $patientIds = array_values(array_unique(array_map('intval', $data['patient_ids'])));

        if ($patientIds === [] || ($kind === AppointmentKind::Individual && count($patientIds) !== 1)) {
            throw SchedulingException::invalidParticipants();
        }

        /** @var Practitioner $practitioner */
        $practitioner = Practitioner::query()
            ->whereKey((int) $data['practitioner_id'])
            ->lockForUpdate()
            ->firstOrFail();
        /** @var Room $room */
        $room = Room::query()
            ->whereKey((int) $data['room_id'])
            ->lockForUpdate()
            ->firstOrFail();
        /** @var TreatmentType $treatmentType */
        $treatmentType = TreatmentType::query()->findOrFail((int) $data['treatment_type_id']);

        if (! (bool) $practitioner->getAttribute('is_active')) {
            throw SchedulingException::inactiveResource('practitioner');
        }

        if (! (bool) $room->getAttribute('is_active')) {
            throw SchedulingException::inactiveResource('room');
        }

        if (! (bool) $treatmentType->getAttribute('is_active')) {
            throw SchedulingException::inactiveResource('treatment type');
        }

        if ((bool) $treatmentType->getAttribute('is_group') !== ($kind === AppointmentKind::Group)) {
            throw SchedulingException::treatmentKindMismatch();
        }

        if (Patient::query()->whereIn('id', $patientIds)->where('is_active', true)->count() !== count($patientIds)) {
            throw SchedulingException::inactiveResource('patient');
        }

        $this->clinicWorkingHoursService->assertWithinWorkingHours($startsAt, $endsAt);
        $this->availabilityService->assertAvailable($practitioner, $startsAt, $endsAt);

        $blockingStatuses = array_map(
            static fn (AppointmentStatus $status): string => $status->value,
            array_filter(AppointmentStatus::cases(), static fn (AppointmentStatus $status): bool => $status->blocksTime()),
        );

        $practitionerConflict = Appointment::query()
            ->where('practitioner_id', $practitioner->getKey())
            ->whereIn('status', $blockingStatuses)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeAppointmentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($excludeAppointmentId))
            ->orderBy('starts_at')
            ->first(['starts_at', 'ends_at']);

        if ($practitionerConflict !== null) {
            throw SchedulingException::practitionerConflict($this->conflictDetails($practitionerConflict->starts_at, $practitionerConflict->ends_at));
        }

        $roomConflict = Appointment::query()
            ->where('room_id', $room->getKey())
            ->whereIn('status', $blockingStatuses)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeAppointmentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($excludeAppointmentId))
            ->orderBy('starts_at')
            ->first(['starts_at', 'ends_at']);

        if ($roomConflict !== null) {
            throw SchedulingException::roomConflict($this->conflictDetails($roomConflict->starts_at, $roomConflict->ends_at));
        }

        foreach (['practitioner_id' => $practitioner->id, 'room_id' => $room->id] as $resource => $resourceId) {
            $occupied = TreatmentSession::query()
                ->where($resource, $resourceId)
                ->where('started_at', '<', $endsAt)
                ->whereNot('status', 'cancelled')
                ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $startsAt))
                ->orderBy('started_at')
                ->first(['id', 'started_at', 'ended_at']);
            if ($occupied !== null) {
                $details = $this->conflictDetails($occupied->started_at, $occupied->ended_at, [
                    'conflict_type' => 'treatment_session',
                    'treatment_session_id' => (int) $occupied->getKey(),
                ]);
                throw $resource === 'room_id'
                    ? SchedulingException::roomConflict($details)
                    : SchedulingException::practitionerConflict($details);
            }
        }

        $appointment = new Appointment;
        $appointment->forceFill([
            'practitioner_id' => $practitioner->getKey(),
            'room_id' => $room->getKey(),
            'treatment_type_id' => $treatmentType->getKey(),
            'rescheduled_from_id' => $rescheduledFromId,
            'kind' => $kind->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $durationMinutes,
            'status' => AppointmentStatus::Scheduled->value,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);
        $appointment->save();

        foreach ($patientIds as $patientId) {
            $participant = new AppointmentParticipant;
            $participant->forceFill([
                'appointment_id' => $appointment->getKey(),
                'patient_id' => $patientId,
                'status' => AppointmentStatus::Scheduled->value,
            ]);
            $participant->save();
        }

        return $appointment->refresh();
    }

    private function dateTime(CarbonInterface|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }

    private function validatedDurationMinutes(mixed $value): int
    {
        if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
            throw SchedulingException::invalidInterval();
        }

        $durationMinutes = (int) $value;

        if ($durationMinutes < 5 || $durationMinutes > 720) {
            throw SchedulingException::invalidInterval();
        }

        return $durationMinutes;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function conflictDetails(CarbonInterface $startsAt, ?CarbonInterface $endsAt, array $details = []): array
    {
        return [
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'open_ended' => $endsAt === null,
            ...$details,
        ];
    }

    private function status(Appointment $appointment): AppointmentStatus
    {
        $status = $appointment->getAttribute('status');

        return $status instanceof AppointmentStatus ? $status : AppointmentStatus::from((string) $status);
    }

    private function kind(Appointment $appointment): AppointmentKind
    {
        $kind = $appointment->getAttribute('kind');

        return $kind instanceof AppointmentKind ? $kind : AppointmentKind::from((string) $kind);
    }

    private function notifyAppointment(
        Appointment $appointment,
        ClinicNotificationType $type,
        string $title,
    ): void {
        $appointment->loadMissing([
            'practitioner:id,user_id,name,telegram_chat_id',
            'room:id,name',
            'treatmentType:id,name',
            'participants.patient:id,full_name',
        ]);
        $patients = $appointment->participants->pluck('patient.full_name')->filter()->join('، ');
        $body = "المريض: {$patients}\n"
            ."المعالج: {$appointment->practitioner?->name}\n"
            ."العلاج: {$appointment->treatmentType?->name}\n"
            .'الموعد: '.ArabicFormat::dateTime($appointment->starts_at)."\n"
            ."الغرفة: {$appointment->room?->name}";

        $this->notifications->operational(
            'appointment:'.$appointment->getKey().':'.$type->value,
            $type,
            $title,
            $body,
            route('appointments.index'),
            ['appointment_id' => $appointment->getKey()],
            $appointment->practitioner,
        );
    }
}
