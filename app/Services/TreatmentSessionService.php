<?php

namespace App\Services;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\ClinicNotificationType;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use App\Enums\TreatmentSessionStatus;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TreatmentSessionService
{
    public function __construct(
        private readonly ClinicNotificationService $notifications,
        private readonly AvailabilityService $availabilityService,
    ) {}

    /**
     * Prices may be keyed by appointment-participant ID or patient ID. A list
     * of arrays with appointment_participant_id/patient_id and original_price
     * is accepted as well.
     *
     * @param  array<int|string, int|array{appointment_participant_id?: int, patient_id?: int, original_price: int, discount_amount?: int}>  $participantPrices
     * @param  array<int>|null  $attendingParticipantIds
     */
    public function createFromAppointment(
        Appointment $appointment,
        array $participantPrices,
        User $actor,
        ?array $attendingParticipantIds = null,
    ): TreatmentSession {
        if (! $actor->hasPermission('sessions.manage')) {
            throw SchedulingException::permissionDenied('sessions.manage');
        }

        return DB::transaction(function () use ($appointment, $participantPrices, $actor, $attendingParticipantIds): TreatmentSession {
            /** @var Appointment $lockedAppointment */
            $lockedAppointment = Appointment::query()
                ->whereKey($appointment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /** @var TreatmentSession|null $existing */
            $existing = TreatmentSession::query()
                ->where('appointment_id', $lockedAppointment->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $appointmentStatus = $this->appointmentStatus($lockedAppointment);

            if (! in_array($appointmentStatus, [AppointmentStatus::Scheduled, AppointmentStatus::Attended], true)) {
                throw SchedulingException::invalidState('converted into a treatment session');
            }

            /** @var Practitioner $practitioner */
            $practitioner = Practitioner::query()->lockForUpdate()->findOrFail((int) $lockedAppointment->getAttribute('practitioner_id'));
            /** @var Room $room */
            $room = Room::query()->lockForUpdate()->findOrFail((int) $lockedAppointment->getAttribute('room_id'));
            /** @var TreatmentType $treatmentType */
            $treatmentType = TreatmentType::query()->withTrashed()->findOrFail((int) $lockedAppointment->getAttribute('treatment_type_id'));

            $actualStart = CarbonImmutable::now();
            if (! $practitioner->is_active || ! $room->is_active || ! $treatmentType->is_active) {
                throw SchedulingException::inactiveResource('session');
            }
            $this->availabilityService->assertAvailableAt($practitioner, $actualStart);
            foreach (['practitioner_id', 'room_id'] as $resource) {
                $appointmentConflict = Appointment::query()->where($resource, $lockedAppointment->$resource)
                    ->whereKeyNot($lockedAppointment->id)
                    ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Attended])
                    ->where('starts_at', '<=', $actualStart)
                    ->where('ends_at', '>', $actualStart)
                    ->exists();
                $sessionConflict = TreatmentSession::query()->where($resource, $lockedAppointment->$resource)
                    ->where('started_at', '<=', $actualStart)
                    ->whereNot('status', TreatmentSessionStatus::Cancelled)
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $actualStart))->exists();
                if ($appointmentConflict || $sessionConflict) {
                    throw $resource === 'room_id' ? SchedulingException::roomConflict() : SchedulingException::practitionerConflict();
                }
            }

            $appointmentParticipants = AppointmentParticipant::query()
                ->where('appointment_id', $lockedAppointment->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($appointmentParticipants->isEmpty()) {
                throw SchedulingException::invalidParticipants();
            }

            $allParticipantIds = $appointmentParticipants
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id);
            $attendingIds = collect($attendingParticipantIds ?? $allParticipantIds->all())
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();

            if ($attendingIds->isEmpty() || $attendingIds->diff($allParticipantIds)->isNotEmpty()) {
                throw SchedulingException::invalidParticipants();
            }

            $kind = $lockedAppointment->getAttribute('kind');
            $appointmentKind = $kind instanceof AppointmentKind ? $kind : AppointmentKind::from((string) $kind);

            if ($appointmentKind === AppointmentKind::Individual && $attendingIds->count() !== 1) {
                throw SchedulingException::invalidParticipants();
            }

            $patientIds = $appointmentParticipants
                ->pluck('patient_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
            $patients = Patient::query()
                ->whereIn('id', $patientIds)
                ->get()
                ->keyBy(static fn (Patient $patient): int => (int) $patient->getKey());

            $session = new TreatmentSession;
            $session->forceFill([
                'appointment_id' => $lockedAppointment->getKey(),
                'practitioner_id' => $practitioner->getKey(),
                'room_id' => $room->getKey(),
                'treatment_type_id' => $treatmentType->getKey(),
                'kind' => $this->enumValue($lockedAppointment->getAttribute('kind')),
                'status' => TreatmentSessionStatus::PendingPayment->value,
                'started_at' => $actualStart,
                'ended_at' => null,
                'practitioner_name' => (string) $practitioner->getAttribute('name'),
                'room_name' => (string) $room->getAttribute('name'),
                'treatment_type_name' => (string) $treatmentType->getAttribute('name'),
                'created_by' => $actor->getKey(),
            ]);
            $session->save();

            foreach ($appointmentParticipants as $appointmentParticipant) {
                if (! $attendingIds->contains((int) $appointmentParticipant->getKey())) {
                    $appointmentParticipant->forceFill(['status' => AppointmentStatus::NoShow->value]);
                    $appointmentParticipant->save();

                    continue;
                }

                $patientId = (int) $appointmentParticipant->getAttribute('patient_id');
                /** @var Patient|null $patient */
                $patient = $patients->get($patientId);

                if ($patient === null) {
                    throw SchedulingException::invalidParticipants();
                }

                $pricing = $participantPrices === []
                    ? ['original_price' => 0, 'discount_amount' => 0, 'final_amount' => 0]
                    : $this->participantPricing($participantPrices, $appointmentParticipant);
                $source = $patient->getAttribute('source');
                $patientSource = $source instanceof PatientSource ? $source : PatientSource::from((string) $source);

                $participant = new TreatmentSessionParticipant;
                $participant->forceFill([
                    'treatment_session_id' => $session->getKey(),
                    'appointment_participant_id' => $appointmentParticipant->getKey(),
                    'patient_id' => $patient->getKey(),
                    'source_practitioner_id' => $patientSource === PatientSource::Practitioner
                        ? $patient->getAttribute('referring_practitioner_id')
                        : null,
                    'patient_name' => (string) $patient->getAttribute('full_name'),
                    'patient_source' => $patientSource->value,
                    'original_price' => $pricing['original_price'],
                    'discount_amount' => $pricing['discount_amount'],
                    'final_amount' => $pricing['final_amount'],
                    'practitioner_percentage' => null,
                    'practitioner_share' => null,
                    'clinic_share' => null,
                    'payment_status' => ParticipantPaymentStatus::Unpaid->value,
                    'discount_approved_by' => null,
                ]);
                $participant->save();
                $appointmentParticipant->forceFill(['status' => AppointmentStatus::Attended->value]);
                $appointmentParticipant->save();
            }

            $lockedAppointment->forceFill([
                'status' => AppointmentStatus::Attended->value,
                'updated_by' => $actor->getKey(),
            ]);
            $lockedAppointment->save();

            return $session->refresh();
        }, 3);
    }

    public function synchronizePaymentStatus(TreatmentSession $session): TreatmentSession
    {
        /** @var TreatmentSession $lockedSession */
        $lockedSession = TreatmentSession::query()
            ->whereKey($session->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $status = $this->sessionStatus($lockedSession);

        if ($status === TreatmentSessionStatus::Cancelled) {
            return $lockedSession;
        }

        $statuses = TreatmentSessionParticipant::query()
            ->where('treatment_session_id', $lockedSession->getKey())
            ->pluck('payment_status')
            ->map(fn (mixed $value): ParticipantPaymentStatus => $this->participantPaymentStatus($value));

        if ($statuses->isNotEmpty() && $statuses->every(
            static fn (ParticipantPaymentStatus $value): bool => $value === ParticipantPaymentStatus::Refunded,
        )) {
            $newStatus = TreatmentSessionStatus::Refunded;
        } elseif ($status === TreatmentSessionStatus::Completed) {
            return $lockedSession;
        } elseif ($statuses->isNotEmpty() && $statuses->every(
            static fn (ParticipantPaymentStatus $value): bool => $value === ParticipantPaymentStatus::Paid,
        )) {
            $newStatus = TreatmentSessionStatus::Ready;
        } else {
            $newStatus = TreatmentSessionStatus::PendingPayment;
        }

        $lockedSession->forceFill(['status' => $newStatus->value]);
        $lockedSession->save();

        return $lockedSession;
    }

    public function endTreatment(
        TreatmentSession $session,
        User $actor,
        CarbonInterface $endedAt,
    ): TreatmentSession {
        if (! $actor->hasPermission('sessions.manage')) {
            throw SchedulingException::permissionDenied('sessions.manage');
        }

        return DB::transaction(function () use ($session, $endedAt): TreatmentSession {
            /** @var TreatmentSession $lockedSession */
            $lockedSession = TreatmentSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSession->getAttribute('ended_at') !== null) {
                return $lockedSession;
            }

            $actualEnd = CarbonImmutable::instance($endedAt)
                ->setTimezone((string) config('app.timezone'));
            $actualStart = CarbonImmutable::instance($lockedSession->getAttribute('started_at'))
                ->setTimezone((string) config('app.timezone'));

            if ($actualEnd->lessThanOrEqualTo($actualStart) || $actualEnd->greaterThan(CarbonImmutable::now())) {
                throw SchedulingException::invalidTreatmentEnd();
            }

            $lockedSession->forceFill(['ended_at' => $actualEnd]);
            $lockedSession->save();

            return $lockedSession->refresh();
        }, 3);
    }

    public function complete(
        TreatmentSession $session,
        User $actor,
        ?CarbonInterface $completedAt = null,
    ): TreatmentSession {
        if (! $actor->hasPermission('sessions.manage')) {
            throw SchedulingException::permissionDenied('sessions.manage');
        }

        $wasCompleted = false;
        $completed = DB::transaction(function () use ($session, $actor, $completedAt, &$wasCompleted): TreatmentSession {
            $synchronized = $this->synchronizePaymentStatus($session);

            if ($this->sessionStatus($synchronized) === TreatmentSessionStatus::Completed) {
                return $synchronized;
            }

            if ($this->sessionStatus($synchronized) !== TreatmentSessionStatus::Ready) {
                throw SchedulingException::unpaidParticipants($this->outstandingParticipantBalances($synchronized));
            }

            $serverNow = CarbonImmutable::now();
            $administrativeCompletion = $completedAt === null
                ? $serverNow
                : CarbonImmutable::instance($completedAt)->setTimezone((string) config('app.timezone'));
            $actualStart = CarbonImmutable::instance($synchronized->getAttribute('started_at'))
                ->setTimezone((string) config('app.timezone'));
            $recordedEnd = $synchronized->getAttribute('ended_at');
            $actualEnd = $recordedEnd === null
                ? $administrativeCompletion
                : CarbonImmutable::instance($recordedEnd)->setTimezone((string) config('app.timezone'));

            if ($actualEnd->lessThanOrEqualTo($actualStart) || $actualEnd->greaterThan($serverNow)) {
                throw SchedulingException::invalidTreatmentEnd();
            }

            if ($administrativeCompletion->lessThan($actualEnd) || $administrativeCompletion->greaterThan($serverNow)) {
                throw SchedulingException::invalidSessionCompletion();
            }

            $synchronized->forceFill([
                'status' => TreatmentSessionStatus::Completed->value,
                'ended_at' => $actualEnd,
                'completed_at' => $administrativeCompletion,
            ]);
            $synchronized->save();
            $wasCompleted = true;

            Appointment::query()
                ->whereKey($synchronized->getAttribute('appointment_id'))
                ->update([
                    'status' => AppointmentStatus::Completed->value,
                    'updated_by' => $actor->getKey(),
                    'updated_at' => now(),
                ]);

            return $synchronized->refresh();
        }, 3);

        if (! $wasCompleted) {
            return $completed;
        }

        $completed->loadMissing([
            'practitioner:id,user_id,name,telegram_chat_id',
            'participants:id,treatment_session_id,patient_name',
        ]);
        $this->notifications->operational(
            'session:'.$completed->getKey().':completed',
            ClinicNotificationType::SessionCompleted,
            '✅ اكتملت جلسة علاجية',
            'المريض: '.$completed->participants->pluck('patient_name')->join('، ')."\n"
                ."المعالج: {$completed->practitioner?->name}\n"
                .'التاريخ: '.$completed->started_at->format('Y/m/d H:i'),
            route('sessions.show', $completed),
            ['treatment_session_id' => $completed->getKey()],
            $completed->practitioner,
        );

        return $completed;
    }

    /**
     * @param  array<int|string, int|array{appointment_participant_id?: int, patient_id?: int, original_price: int, discount_amount?: int}>  $prices
     * @return array{original_price: int, discount_amount: int, final_amount: int}
     */
    private function participantPricing(array $prices, AppointmentParticipant $participant): array
    {
        $participantId = (int) $participant->getKey();
        $patientId = (int) $participant->getAttribute('patient_id');
        $value = $prices[$participantId] ?? $prices[(string) $participantId]
            ?? $prices[$patientId] ?? $prices[(string) $patientId] ?? null;

        if ($value === null) {
            foreach ($prices as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $matchesParticipant = isset($candidate['appointment_participant_id'])
                    && (int) $candidate['appointment_participant_id'] === $participantId;
                $matchesPatient = isset($candidate['patient_id'])
                    && (int) $candidate['patient_id'] === $patientId;

                if ($matchesParticipant || $matchesPatient) {
                    $value = $candidate;

                    break;
                }
            }
        }

        $originalValue = is_array($value) ? ($value['original_price'] ?? null) : $value;
        $discountValue = is_array($value) ? ($value['discount_amount'] ?? 0) : 0;
        $price = is_int($originalValue) ? $originalValue : (filter_var($originalValue, FILTER_VALIDATE_INT) ?: 0);
        $discount = is_int($discountValue) ? $discountValue : filter_var($discountValue, FILTER_VALIDATE_INT);

        if ($price <= 0 || $discount === false || $discount < 0 || $discount >= $price) {
            throw SchedulingException::missingParticipantPrice($participantId);
        }

        return [
            'original_price' => $price,
            'discount_amount' => $discount,
            'final_amount' => $price - $discount,
        ];
    }

    private function appointmentStatus(Appointment $appointment): AppointmentStatus
    {
        $status = $appointment->getAttribute('status');

        return $status instanceof AppointmentStatus ? $status : AppointmentStatus::from((string) $status);
    }

    private function sessionStatus(TreatmentSession $session): TreatmentSessionStatus
    {
        $status = $session->getAttribute('status');

        return $status instanceof TreatmentSessionStatus ? $status : TreatmentSessionStatus::from((string) $status);
    }

    private function participantPaymentStatus(mixed $status): ParticipantPaymentStatus
    {
        return $status instanceof ParticipantPaymentStatus
            ? $status
            : ParticipantPaymentStatus::from((string) $status);
    }

    /** @return list<array{name: string, amount: int}> */
    private function outstandingParticipantBalances(TreatmentSession $session): array
    {
        return TreatmentSessionParticipant::query()
            ->with('payment.refunds')
            ->where('treatment_session_id', $session->getKey())
            ->where('payment_status', '!=', ParticipantPaymentStatus::Paid->value)
            ->orderBy('id')
            ->get()
            ->map(function (TreatmentSessionParticipant $participant): array {
                $payment = $participant->payment;
                $netPaid = $payment === null
                    ? 0
                    : max(0, (int) $payment->amount - (int) $payment->refunds->sum('amount'));

                return [
                    'name' => (string) $participant->patient_name,
                    'amount' => max(0, (int) $participant->final_amount - $netPaid),
                ];
            })
            ->all();
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
