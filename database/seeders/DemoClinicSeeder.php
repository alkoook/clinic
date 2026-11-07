<?php

namespace Database\Seeders;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\AvailabilityExceptionType;
use App\Enums\CashboxType;
use App\Enums\PatientSource;
use App\Models\Cashbox;
use App\Models\ExpenseCategory;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\PractitionerAvailabilityException;
use App\Models\Role;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\TreatmentType;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\CashboxService;
use App\Services\CashboxTransferService;
use App\Services\ExpenseService;
use App\Services\FinancialAdjustmentService;
use App\Services\FinancialAgreementService;
use App\Services\FinancialWeekService;
use App\Services\PaymentService;
use App\Services\PractitionerSettlementService;
use App\Services\RefundService;
use App\Services\SettingsService;
use App\Services\TreatmentSessionService;
use App\Services\WeeklyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoClinicSeeder extends Seeder
{
    private const PASSWORD = 'Clinic@123456';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('لا يمكن إنشاء بيانات العرض خارج بيئة local أو testing.');
        }

        $this->call([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
        app(SettingsService::class)->setMany([
            'telegram.enabled' => ['group' => 'telegram', 'value' => '0', 'type' => 'boolean'],
            'clinic.name' => ['group' => 'clinic', 'value' => 'عيادة الحياة', 'type' => 'string'],
            'clinic.short_name' => ['group' => 'clinic', 'value' => 'الحياة', 'type' => 'string'],
            'clinic.working_hours' => [
                'group' => 'clinic',
                'value' => json_encode(collect(range(0, 6))->mapWithKeys(fn (int $day): array => [$day => ['enabled' => true, 'start' => '07:00', 'end' => '23:00']])->all(), JSON_UNESCAPED_UNICODE),
                'type' => 'json',
            ],
        ]);

        $users = $this->users();
        [$practitioners, $treatments, $rooms] = $this->masterData($users);
        $this->agreements($practitioners, $treatments, $users['admin']);
        $this->availability($practitioners);
        $patients = $this->patients($practitioners, $users['secretary']);
        $this->clinicalAndFinancialStory($users, $practitioners, $treatments, $rooms, $patients);

        $this->command?->newLine();
        $this->command?->info('تم تجهيز بيانات عيادة الحياة للعرض.');
        foreach (['admin', 'secretary', 'doctor1', 'doctor2', 'therapist1'] as $username) {
            $this->command?->line("  {$username} / ".self::PASSWORD);
        }
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $roles = Role::query()->whereIn('name', ['super-admin', 'secretary', 'practitioner'])->get()->keyBy('name');
        $definitions = [
            'admin' => ['name' => 'مدير عيادة الحياة', 'role' => 'super-admin'],
            'secretary' => ['name' => 'لينا منصور — السكرتارية', 'role' => 'secretary'],
            'doctor1' => ['name' => 'د. محمد خالد', 'role' => 'practitioner'],
            'doctor2' => ['name' => 'د. ريم الأحمد', 'role' => 'practitioner'],
            'therapist1' => ['name' => 'أ. سامر العلي', 'role' => 'practitioner'],
            'therapist2' => ['name' => 'أ. نور منصور', 'role' => 'practitioner'],
            'therapist3' => ['name' => 'أ. ليلى شاهين', 'role' => 'practitioner'],
            'doctor3' => ['name' => 'د. هالة النجار', 'role' => 'practitioner'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $username) use ($roles): array {
            $user = User::query()->updateOrCreate(['username' => $username], [
                'role_id' => $roles[$definition['role']]->getKey(),
                'name' => $definition['name'],
                'email' => "{$username}@clinic.demo",
                'password' => self::PASSWORD,
                'is_active' => true,
            ]);

            return [$username => $user];
        })->all();
    }

    /** @return array{0: array<string, Practitioner>, 1: array<string, TreatmentType>, 2: array<string, Room>} */
    private function masterData(array $users): array
    {
        $specialties = collect([
            'mental' => 'الاضطرابات النفسية', 'addiction' => 'الاضطرابات الإدمانية',
            'family' => 'العلاج الأسري', 'behavior' => 'العلاج السلوكي', 'speech' => 'اضطرابات النطق والنمو',
        ])->mapWithKeys(fn (string $name, string $key): array => [$key => Specialty::query()->updateOrCreate(['name' => $name], ['is_active' => true])])->all();

        $treatmentDefinitions = [
            'psych' => ['جلسة علاج نفسي فردي', 'mental', false], 'behavior' => ['جلسة علاج سلوكي', 'behavior', false],
            'family' => ['جلسة علاج أسري', 'family', false], 'group' => ['جلسة علاج جماعي', 'family', true],
            'addiction' => ['جلسة متابعة إدمان', 'addiction', false], 'speech_eval' => ['جلسة تقييم نطق', 'speech', false],
            'speech' => ['جلسة علاج نطق', 'speech', false],
        ];
        $treatments = collect($treatmentDefinitions)->mapWithKeys(function (array $definition, string $key) use ($specialties): array {
            return [$key => TreatmentType::query()->updateOrCreate(['name' => $definition[0]], [
                'specialty_id' => $specialties[$definition[1]]->getKey(), 'is_group' => $definition[2], 'is_active' => true,
            ])];
        })->all();

        $practitionerDefinitions = [
            'doctor1' => ['د. محمد خالد', '0933112201', ['mental', 'behavior', 'addiction']],
            'doctor2' => ['د. ريم الأحمد', '0933112202', ['family', 'mental']],
            'therapist1' => ['أ. سامر العلي', '0933112203', ['speech']],
            'therapist2' => ['أ. نور منصور', '0933112204', ['behavior']],
            'therapist3' => ['أ. ليلى شاهين', '0933112205', ['speech', 'behavior']],
            'doctor3' => ['د. هالة النجار', '0933112206', ['mental', 'family']],
        ];
        $practitioners = collect($practitionerDefinitions)->mapWithKeys(function (array $definition, string $key) use ($users, $specialties): array {
            $practitioner = Practitioner::query()->updateOrCreate(['user_id' => $users[$key]->getKey()], [
                'name' => $definition[0], 'phone' => $definition[1], 'is_active' => true,
                'administrative_notes' => 'حساب تجريبي متكامل لعرض صلاحيات المعالج.',
            ]);
            $practitioner->specialties()->sync(collect($definition[2])->map(fn (string $specialty) => $specialties[$specialty]->getKey())->all());

            return [$key => $practitioner];
        })->all();

        $rooms = collect([
            'r1' => ['غرفة 1 — العلاج الفردي', 'R-01'], 'r2' => ['غرفة 2 — العلاج الفردي', 'R-02'],
            'r3' => ['غرفة 3 — الجلسات الجماعية والأسرية', 'R-03'],
        ])->mapWithKeys(fn (array $room, string $key): array => [$key => Room::query()->updateOrCreate(['code' => $room[1]], ['name' => $room[0], 'is_active' => true])])->all();

        foreach (['إيجار', 'كهرباء', 'ماء', 'إنترنت', 'ضيافة', 'صيانة', 'تجهيزات', 'رواتب', 'أخرى'] as $category) {
            ExpenseCategory::query()->updateOrCreate(['name' => $category], ['is_active' => true]);
        }

        return [$practitioners, $treatments, $rooms];
    }

    private function agreements(array $practitioners, array $treatments, User $admin): void
    {
        $service = app(FinancialAgreementService::class);
        $definitions = [
            'doctor1' => 60, 'doctor2' => 55, 'doctor3' => 45,
            'therapist1' => 40, 'therapist2' => 30, 'therapist3' => 35,
        ];
        foreach ($definitions as $practitioner => $rate) {
            $service->create([
                'practitioner_id' => $practitioners[$practitioner]->getKey(),
                'practitioner_percentage' => $rate,
                'effective_from' => now()->subYear()->toDateString(),
                'notes' => 'اتفاق ساري مع حفظ النسبة كلقطة زمنية ثابتة عند الدفع.',
            ], $admin);
        }
    }

    private function availability(array $practitioners): void
    {
        $schedule = [];
        foreach (array_keys($practitioners) as $key) {
            $schedule[$key] = [];
            foreach ([6, 0, 1, 2, 3, 4] as $day) {
                $schedule[$key][] = [$day, '08:00', '13:00'];
                $schedule[$key][] = [$day, '14:00', '20:00'];
            }
        }
        foreach ($schedule as $key => $intervals) {
            foreach ($intervals as [$day, $start, $end]) {
                PractitionerAvailability::query()->create([
                    'practitioner_id' => $practitioners[$key]->getKey(), 'day_of_week' => $day,
                    'start_time' => $start, 'end_time' => $end, 'effective_from' => now()->subYear(), 'is_active' => true,
                ]);
            }
        }
        PractitionerAvailabilityException::query()->create([
            'practitioner_id' => $practitioners['doctor1']->getKey(), 'date' => now()->addDays(10)->toDateString(),
            'type' => AvailabilityExceptionType::Unavailable, 'reason' => 'إجازة طارئة تجريبية',
        ]);
        PractitionerAvailabilityException::query()->create([
            'practitioner_id' => $practitioners['doctor2']->getKey(), 'date' => now()->addDays(8)->toDateString(),
            'type' => AvailabilityExceptionType::Available, 'start_time' => '20:00', 'end_time' => '21:00', 'reason' => 'فترة مسائية إضافية للمتابعة',
        ]);
        foreach ($practitioners as $index => $practitioner) {
            PractitionerAvailabilityException::query()->create([
                'practitioner_id' => $practitioner->id, 'date' => now()->next(5)->toDateString(),
                'type' => AvailabilityExceptionType::Available, 'start_time' => '10:00', 'end_time' => '12:00',
                'reason' => 'فترة إضافية اختيارية للمتابعات',
            ]);
            PractitionerAvailabilityException::query()->create([
                'practitioner_id' => $practitioner->id, 'date' => now()->addWeek()->toDateString(),
                'type' => AvailabilityExceptionType::Unavailable, 'start_time' => '16:00', 'end_time' => '18:00',
                'reason' => 'استراحة تدريب مهني',
            ]);
        }
    }

    /** @return array<int, Patient> */
    private function patients(array $practitioners, User $secretary): array
    {
        $names = ['أحمد الخطيب', 'محمد الحسن', 'سارة العلي', 'ريم محمود', 'يزن درويش', 'نور الأحمد', 'خالد يوسف', 'مريم حمود', 'لينا مصطفى', 'عمر شاهين', 'رنا سليمان', 'جود النجار', 'كرم حداد', 'هبة عثمان', 'ورد المصري', 'مازن ديوب', 'تالا إبراهيم', 'فادي حيدر', 'شام منصور', 'وسيم مراد', 'آية خليل', 'زين العابدين', 'لارا حمزة', 'باسل قدور'];
        $firstNames = ['أحمد', 'سارة', 'ليان', 'عمر', 'ريم', 'يزن', 'نور', 'تالا', 'كريم', 'مريم'];
        $families = ['الخطيب', 'النجار', 'شاهين', 'منصور', 'الحموي', 'درويش', 'الحسن', 'العلي'];
        $middleNames = ['محمود', 'خالد', 'ياسر', 'سليم', 'نزار', 'فراس', 'مازن', 'عماد'];
        $names = [];
        foreach ($families as $family) {
            foreach ($firstNames as $firstName) {
                $names[] = $firstName.' '.$family;
            }
        }
        $keys = array_keys($practitioners);
        $fileNumbers = [];

        return collect($names)->map(function (string $name, int $index) use ($practitioners, $secretary, $keys, $middleNames, &$fileNumbers): Patient {
            $practitioner = $practitioners[$keys[$index % count($keys)]];
            $practitionerId = $practitioner->getKey();
            $fileNumbers[$practitionerId] = ($fileNumbers[$practitionerId] ?? 0) + 1;
            $isReferred = $index % 4 === 1;
            [$firstName, $familyName] = explode(' ', $name, 2);
            $patient = Patient::query()->create([
                'medical_file_number' => (string) $fileNumbers[$practitionerId],
                'full_name' => $firstName.' '.$middleNames[$index % count($middleNames)].' '.$familyName,
                'age' => 5 + ($index % 71),
                'gender' => $index % 2 === 0 ? 'male' : 'female',
                'marital_status' => ['single', 'married', 'divorced', 'widowed'][$index % 4],
                'occupation' => ['طالب', 'موظف', 'ربة منزل', 'معلم', 'محاسب'][$index % 5],
                'companion_name' => ['سليم الأحمد', 'رائد النجار', 'حنان إبراهيم', 'ليلى الخطيب'][$index % 4],
                'phone' => '09'.str_pad((string) (33000000 + $index * 137), 8, '0', STR_PAD_LEFT),
                'incoming_hospital_name' => $index % 7 === 0 ? 'مشفى المواساة' : ($index % 9 === 0 ? 'مشفى الرازي' : null),
                'source' => $isReferred ? PatientSource::Practitioner : PatientSource::Clinic,
                'referring_practitioner_id' => $isReferred ? $practitioner->getKey() : null,
                'current_practitioner_id' => $practitionerId, 'is_active' => true,
                'administrative_notes' => $index % 6 === 0 ? 'يفضّل المواعيد المسائية.' : null,
                'created_by' => $secretary->getKey(), 'updated_by' => $secretary->getKey(),
            ]);
            $patient->forceFill(['created_at' => now()->subDays(($index * 5) % 120), 'updated_at' => now()->subDays(($index * 2) % 30)])->save();

            return $patient;
        })->all();
    }

    private function clinicalAndFinancialStory(array $users, array $practitioners, array $treatments, array $rooms, array $patients): void
    {
        $realNow = CarbonImmutable::now();
        $originalTestNow = CarbonImmutable::getTestNow();
        $admin = $users['admin'];
        $weeks = app(FinancialWeekService::class);
        $currentStart = $weeks->boundariesFor($realNow)['starts_on'];
        $begin = $currentStart->subWeeks(2);
        $main = Cashbox::query()->where('type', CashboxType::Main)->firstOrFail();
        $side = Cashbox::query()->where('type', CashboxType::Side)->firstOrFail();
        $cash = app(CashboxService::class);
        $payments = [];
        $keys = array_keys($practitioners);
        $treatmentKeys = ['psych', 'family', 'speech', 'behavior', 'speech', 'psych'];
        try {
            Carbon::setTestNow($begin);
            CarbonImmutable::setTestNow($begin);
            $cash->deposit($main, 15_000_000, 'رأس مال تشغيلي افتتاحي', $admin, (string) Str::uuid(), $begin);
            $cash->deposit($side, 8_000_000, 'احتياطي نقدي افتتاحي', $admin, (string) Str::uuid(), $begin);
            $sequence = 0;
            for ($dayIndex = 0; $dayIndex < 29; $dayIndex++) {
                $date = $begin->addDays($dayIndex);
                if ($date->dayOfWeek === 5) {
                    continue;
                }
                foreach ($keys as $index => $key) {
                    $time = $date->setTime($index < 3 ? 9 : 11, 0);
                    if (! app(AvailabilityService::class)->isAvailable($practitioners[$key], $time, $time->addHour())) {
                        continue;
                    }
                    $sequence++;
                    $group = $index === 1 && $dayIndex % 4 === 0;
                    $treatment = $treatments[$group ? 'group' : $treatmentKeys[$index]];
                    $patientIds = [$patients[$sequence % count($patients)]->id];
                    if ($group) {
                        $patientIds[] = $patients[($sequence + 21) % count($patients)]->id;
                        $patientIds[] = $patients[($sequence + 42) % count($patients)]->id;
                    }
                    $appointment = app(AppointmentService::class)->book([
                        'practitioner_id' => $practitioners[$key]->id,
                        'room_id' => $rooms['r'.($index % 3 + 1)]->id,
                        'treatment_type_id' => $treatment->id,
                        'starts_at' => $time, 'duration_minutes' => 60,
                        'kind' => $group ? AppointmentKind::Group : AppointmentKind::Individual,
                        'patient_ids' => $patientIds,
                        'notes' => $group ? 'جلسة دعم ومهارات مشتركة' : 'متابعة الخطة العلاجية',
                    ], $users['secretary']);
                    if ($sequence % 11 === 0) {
                        app(AppointmentService::class)->cancel($appointment, 'اعتذار المريض لظرف عائلي', $users['secretary']);
                    } elseif ($sequence % 13 === 0) {
                        app(AppointmentService::class)->reschedule($appointment, ['starts_at' => $time->addHour()], $users['secretary']);
                    } elseif ($time->addHour()->lessThan($realNow)) {
                        Carbon::setTestNow($time);
                        CarbonImmutable::setTestNow($time);
                        if ($sequence % 9 === 0) {
                            DB::transaction(function () use ($appointment): void {
                                $appointment->update(['status' => AppointmentStatus::NoShow]);
                                $appointment->participants()->update(['status' => AppointmentStatus::NoShow->value]);
                            });

                            continue;
                        }
                        $session = app(TreatmentSessionService::class)->createFromAppointment($appointment, [], $admin);
                        foreach ($session->participants as $participant) {
                            $price = 100_000 + ($sequence % 5) * 25_000;
                            $discount = $sequence % 7 === 0 ? 10_000 : 0;
                            $payments[] = app(PaymentService::class)->pay($participant, $price, $discount, $admin, (string) Str::uuid(), $time);
                        }
                        $actualEnd = $time->addMinutes(45 + $sequence % 15);
                        Carbon::setTestNow($actualEnd);
                        CarbonImmutable::setTestNow($actualEnd);
                        app(TreatmentSessionService::class)->endTreatment($session, $admin, $actualEnd);
                        app(TreatmentSessionService::class)->complete($session, $admin, $actualEnd);
                    }
                }
            }
            Carbon::setTestNow($realNow);
            CarbonImmutable::setTestNow($realNow);
            foreach ([$begin, $begin->addWeek()] as $index => $start) {
                app(ExpenseService::class)->record(ExpenseCategory::query()->where('name', $index ? 'كهرباء' : 'إيجار')->firstOrFail(),
                    $main, $index ? 450_000 : 2_000_000, $index ? 'فاتورة الكهرباء الشهرية' : 'إيجار مقر العيادة', true, $admin, (string) Str::uuid(), $start->addDays(4));
                $closing = app(WeeklyClosingService::class)->close($weeks->forDate($start), $admin, (string) Str::uuid());
                $settlement = $closing->financialWeek->practitionerSettlements()->where('remaining_amount', '>', 0)->orderByDesc('remaining_amount')->firstOrFail();
                app(PractitionerSettlementService::class)->pay($settlement, min(100_000, (int) $settlement->remaining_amount - 1), $admin, (string) Str::uuid(), $start->addDays(6));
            }
            app(RefundService::class)->refund($payments[0], 25_000, 'استرداد جزئي موثق بعد إغلاق الأسبوع', $admin, (string) Str::uuid(), $realNow);
            app(RefundService::class)->refund($payments[1], $payments[1]->amount, 'استرداد كامل بناء على طلب المريض', $admin, (string) Str::uuid(), $realNow);
            app(FinancialAdjustmentService::class)->record($weeks->current(), $practitioners['doctor1'], 75_000, 'مكافأة جلسات متابعة إضافية', $admin, (string) Str::uuid(), $realNow);
            app(FinancialAdjustmentService::class)->record($weeks->current(), $practitioners['doctor2'], -20_000, 'تصحيح مستحق سابق موثق', $admin, (string) Str::uuid(), $realNow);
            app(ExpenseService::class)->record(ExpenseCategory::query()->where('name', 'ضيافة')->firstOrFail(), $main, 80_000, 'مستلزمات استقبال المرضى', true, $admin, (string) Str::uuid(), $realNow);
            app(CashboxTransferService::class)->transfer($side, $main, 500_000, 'تعزيز السيولة التشغيلية', $admin, (string) Str::uuid(), $realNow);
            $cash->withdraw($side, 100_000, 'تجهيزات احتياطية للعيادة', $admin, (string) Str::uuid(), $realNow);
        } finally {
            Carbon::setTestNow($originalTestNow);
            CarbonImmutable::setTestNow($originalTestNow);
        }
    }
}
