<?php

namespace Database\Seeders;

use App\Enums\CashboxType;
use App\Models\Cashbox;
use App\Models\ExpenseCategory;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\TreatmentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $settings = [
                'clinic.name' => ['group' => 'clinic', 'value' => 'عيادة الحياة الطيبة', 'type' => 'string'],
                'clinic.phone' => ['group' => 'clinic', 'value' => '', 'type' => 'string'],
                'clinic.address' => ['group' => 'clinic', 'value' => '', 'type' => 'string'],
                'appointment.default_duration' => ['group' => 'appointment', 'value' => '60', 'type' => 'integer'],
                'financial.currency' => ['group' => 'financial', 'value' => 'SYP', 'type' => 'string'],
                'appearance.default_theme' => ['group' => 'appearance', 'value' => 'light', 'type' => 'string'],
            ];

            foreach ($settings as $key => $attributes) {
                Setting::query()->updateOrCreate(['key' => $key], $attributes);
            }

            $specialties = collect([
                'الطب العام',
                'العلاج الفيزيائي',
                'الصحة النفسية',
            ])->mapWithKeys(function (string $name): array {
                $specialty = Specialty::query()->updateOrCreate(['name' => $name], ['is_active' => true]);

                return [$name => $specialty];
            });

            $treatmentTypes = [
                ['name' => 'استشارة طبية', 'specialty' => 'الطب العام', 'is_group' => false],
                ['name' => 'جلسة علاج فيزيائي', 'specialty' => 'العلاج الفيزيائي', 'is_group' => false],
                ['name' => 'جلسة دعم نفسي فردية', 'specialty' => 'الصحة النفسية', 'is_group' => false],
                ['name' => 'جلسة علاج جماعي', 'specialty' => 'الصحة النفسية', 'is_group' => true],
            ];

            foreach ($treatmentTypes as $definition) {
                TreatmentType::query()->updateOrCreate(
                    ['name' => $definition['name']],
                    [
                        'specialty_id' => $specialties[$definition['specialty']]->id,
                        'is_group' => $definition['is_group'],
                        'is_active' => true,
                    ],
                );
            }

            foreach ([
                ['name' => 'الغرفة الأولى', 'code' => 'R-01'],
                ['name' => 'الغرفة الثانية', 'code' => 'R-02'],
                ['name' => 'الغرفة الثالثة', 'code' => 'R-03'],
            ] as $room) {
                Room::query()->updateOrCreate(['code' => $room['code']], $room + ['is_active' => true]);
            }

            Cashbox::query()->updateOrCreate(
                ['type' => CashboxType::Main],
                ['name' => CashboxType::Main->label(), 'is_active' => true],
            );
            Cashbox::query()->updateOrCreate(
                ['type' => CashboxType::Side],
                ['name' => CashboxType::Side->label(), 'is_active' => true],
            );

            foreach (['مستلزمات طبية', 'خدمات وتشغيل', 'صيانة', 'أجور ورواتب', 'مصروفات أخرى'] as $category) {
                ExpenseCategory::query()->updateOrCreate(['name' => $category], ['is_active' => true]);
            }
        });
    }
}
