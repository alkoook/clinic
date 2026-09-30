<?php

namespace App\Console\Commands;

use Database\Seeders\DemoClinicSeeder;
use Illuminate\Console\Command;

class ResetClinicDemo extends Command
{
    protected $signature = 'clinic:demo-reset {--force : تأكيد إعادة إنشاء قاعدة بيانات العرض}';

    protected $description = 'إعادة قاعدة بيانات العرض المحلية وملئها بسيناريو عيادة متكامل';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('هذا الأمر محظور خارج بيئة local أو testing.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('سيتم حذف بيانات قاعدة البيئة الحالية وإعادة إنشائها. هل تريد المتابعة؟')) {
            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoClinicSeeder::class, '--force' => true]);
        $this->newLine();
        $this->info('بيانات الدخول: admin, secretary, doctor1, doctor2, therapist1');
        $this->info('كلمة المرور لجميع الحسابات: Clinic@123456');

        return self::SUCCESS;
    }
}
