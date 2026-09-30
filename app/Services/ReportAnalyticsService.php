<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentSessionStatus;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReportAnalyticsService
{
    public function rooms(?string $from, ?string $to): Builder
    {
        [$fromAt, $toAt, $midpoint] = $this->period($from, $to);
        $appointmentScope = fn (Builder $query): Builder => $query->whereBetween('starts_at', [$fromAt, $toAt]);
        $sessionScope = fn (Builder $query): Builder => $query->whereBetween('started_at', [$fromAt, $toAt]);

        return Room::query()
            ->select('rooms.*')
            ->withCount([
                'appointments as appointments_count' => $appointmentScope,
                'appointments as cancelled_count' => fn (Builder $query): Builder => $appointmentScope($query)->where('status', AppointmentStatus::Cancelled),
                'appointments as no_show_count' => fn (Builder $query): Builder => $appointmentScope($query)->where('status', AppointmentStatus::NoShow),
                'treatmentSessions as sessions_count' => $sessionScope,
                'treatmentSessions as completed_sessions_count' => fn (Builder $query): Builder => $sessionScope($query)->where('status', TreatmentSessionStatus::Completed),
                'treatmentSessions as earlier_sessions_count' => fn (Builder $query): Builder => $query->whereBetween('started_at', [$fromAt, $midpoint]),
                'treatmentSessions as recent_sessions_count' => fn (Builder $query): Builder => $query->whereBetween('started_at', [$midpoint->addSecond(), $toAt]),
            ])
            ->withSum(['appointments as booked_minutes' => $appointmentScope], 'duration_minutes')
            ->selectSub(
                TreatmentSessionParticipant::query()
                    ->join('treatment_sessions', 'treatment_sessions.id', '=', 'treatment_session_participants.treatment_session_id')
                    ->join('payments', 'payments.treatment_session_participant_id', '=', 'treatment_session_participants.id')
                    ->selectRaw('COALESCE(SUM(payments.amount), 0)')
                    ->whereColumn('treatment_sessions.room_id', 'rooms.id')
                    ->whereBetween('treatment_sessions.started_at', [$fromAt, $toAt]),
                'room_revenue',
            )
            ->orderByDesc('booked_minutes');
    }

    public function treatments(?string $from, ?string $to): Builder
    {
        [$fromAt, $toAt, $midpoint] = $this->period($from, $to);
        $participants = fn () => TreatmentSessionParticipant::query()
            ->join('treatment_sessions', 'treatment_sessions.id', '=', 'treatment_session_participants.treatment_session_id')
            ->whereColumn('treatment_sessions.treatment_type_id', 'treatment_types.id')
            ->whereBetween('treatment_sessions.started_at', [$fromAt, $toAt]);

        $practitionerDistribution = DB::connection()->getDriverName() === 'mysql'
            ? "COALESCE(GROUP_CONCAT(DISTINCT practitioner_name ORDER BY practitioner_name SEPARATOR '، '), '')"
            : "COALESCE(GROUP_CONCAT(DISTINCT practitioner_name), '')";

        return TreatmentType::query()
            ->select('treatment_types.*')
            ->with('specialty:id,name')
            ->selectSub($participants()->selectRaw('COUNT(*)'), 'sessions_count')
            ->selectSub($participants()->selectRaw('COUNT(DISTINCT treatment_session_participants.patient_id)'), 'patients_count')
            ->selectSub($participants()->join('payments', 'payments.treatment_session_participant_id', '=', 'treatment_session_participants.id')->selectRaw('COALESCE(SUM(payments.amount), 0)'), 'treatment_revenue')
            ->selectSub($participants()->selectRaw('COUNT(*)')->whereBetween('treatment_sessions.started_at', [$fromAt, $midpoint]), 'earlier_sessions_count')
            ->selectSub($participants()->selectRaw('COUNT(*)')->whereBetween('treatment_sessions.started_at', [$midpoint->addSecond(), $toAt]), 'recent_sessions_count')
            ->selectSub(
                TreatmentSession::query()->selectRaw($practitionerDistribution)
                    ->whereColumn('treatment_sessions.treatment_type_id', 'treatment_types.id')
                    ->whereBetween('started_at', [$fromAt, $toAt]),
                'practitioner_distribution',
            )
            ->selectSub(
                $participants()->selectRaw('COUNT(DISTINCT treatment_session_participants.patient_id)')
                    ->whereNotExists(function ($query) use ($fromAt): void {
                        $query->select(DB::raw(1))
                            ->from('treatment_session_participants as prior_participants')
                            ->join('treatment_sessions as prior_sessions', 'prior_sessions.id', '=', 'prior_participants.treatment_session_id')
                            ->whereColumn('prior_participants.patient_id', 'treatment_session_participants.patient_id')
                            ->where('prior_sessions.started_at', '<', $fromAt);
                    }),
                'new_patients_count',
            )
            ->orderByDesc('treatment_revenue')
            ->orderByDesc('sessions_count');
    }

    /** @return array{CarbonImmutable, CarbonImmutable, CarbonImmutable} */
    private function period(?string $from, ?string $to): array
    {
        $fromAt = CarbonImmutable::parse($from ?: now()->startOfMonth()->toDateString())->startOfDay();
        $toAt = CarbonImmutable::parse($to ?: now()->toDateString())->endOfDay();
        $midpoint = $fromAt->addSeconds((int) floor($fromAt->diffInSeconds($toAt) / 2));

        return [$fromAt, $toAt, $midpoint];
    }
}
