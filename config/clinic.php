<?php

return [
    'name' => 'عيادة الحياة الطيبة',
    'short_name' => 'الحياة الطيبة',
    'locale' => 'ar',

    'auth' => [
        'login' => [
            'max_attempts' => 5,
            'decay_seconds' => 60,
        ],
    ],

    'scheduling' => [
        'early_session_start_minutes' => 30,
    ],

    'development_admin' => [
        'name' => env('CLINIC_ADMIN_NAME', 'مدير النظام'),
        'username' => env('CLINIC_ADMIN_USERNAME'),
        'email' => env('CLINIC_ADMIN_EMAIL'),
        'password' => env('CLINIC_ADMIN_PASSWORD'),
    ],

    'permissions' => [
        'dashboard.operational.view' => ['label' => 'عرض لوحة التشغيل', 'group' => 'لوحات المعلومات'],
        'financial.dashboard.view' => ['label' => 'عرض لوحة الإدارة المالية', 'group' => 'لوحات المعلومات'],
        'patients.view' => ['label' => 'عرض جميع المرضى', 'group' => 'المرضى'],
        'patients.view_own' => ['label' => 'عرض مرضى الطبيب / المعالج', 'group' => 'المرضى'],
        'patients.create' => ['label' => 'إنشاء ملف مريض', 'group' => 'المرضى'],
        'patients.update' => ['label' => 'تعديل ملف مريض', 'group' => 'المرضى'],
        'patient_attachments.view' => ['label' => 'عرض مرفقات المرضى', 'group' => 'المرضى'],
        'patient_attachments.upload' => ['label' => 'رفع مرفقات المرضى', 'group' => 'المرضى'],
        'patient_attachments.preview' => ['label' => 'معاينة مرفقات المرضى', 'group' => 'المرضى'],
        'patient_attachments.download' => ['label' => 'تنزيل مرفقات المرضى', 'group' => 'المرضى'],
        'appointments.manage' => ['label' => 'إدارة المواعيد', 'group' => 'المواعيد والجلسات'],
        'appointments.view_own' => ['label' => 'عرض مواعيد الطبيب / المعالج', 'group' => 'المواعيد والجلسات'],
        'practitioner.availability.manage_own' => ['label' => 'إدارة أوقات التوفر الخاصة', 'group' => 'المواعيد والجلسات'],
        'sessions.manage' => ['label' => 'إدارة الجلسات', 'group' => 'المواعيد والجلسات'],
        'sessions.view_own' => ['label' => 'عرض جلسات الطبيب / المعالج', 'group' => 'المواعيد والجلسات'],
        'payments.create' => ['label' => 'تسجيل الدفعات', 'group' => 'المالية'],
        'discounts.apply' => ['label' => 'تطبيق الخصومات', 'group' => 'المالية'],
        'refunds.create' => ['label' => 'تنفيذ الاستردادات', 'group' => 'المالية'],
        'expenses.manage' => ['label' => 'إدارة المصروفات', 'group' => 'المالية'],
        'financial.agreements.manage' => ['label' => 'إدارة الاتفاقات المالية', 'group' => 'المالية'],
        'settlements.manage' => ['label' => 'إدارة مستحقات الأطباء والمعالجين', 'group' => 'المالية'],
        'cashboxes.manage' => ['label' => 'إدارة الصناديق', 'group' => 'المالية'],
        'reports.view' => ['label' => 'عرض جميع التقارير', 'group' => 'التقارير'],
        'reports.view_own' => ['label' => 'عرض التقارير الشخصية', 'group' => 'التقارير'],
        'practitioners.manage' => ['label' => 'إدارة الأطباء والمعالجين', 'group' => 'الإدارة'],
        'master_data.manage' => ['label' => 'إدارة البيانات الأساسية', 'group' => 'الإدارة'],
        'rooms.view' => ['label' => 'عرض غرف العيادة', 'group' => 'الإدارة'],
        'users.manage' => ['label' => 'إدارة المستخدمين', 'group' => 'الإدارة'],
        'settings.manage' => ['label' => 'إدارة الإعدادات', 'group' => 'الإدارة'],
        'audit.view' => ['label' => 'عرض سجل التدقيق', 'group' => 'الإدارة'],
    ],

    'navigation' => [
        [
            'label' => 'التشغيل اليومي',
            'items' => [
                ['label' => 'لوحة التشغيل', 'route' => 'dashboard', 'icon' => 'dashboard', 'ability' => ['dashboard.operational.view', 'financial.dashboard.view', 'reports.view_own']],
                ['label' => 'المواعيد', 'route' => 'appointments.index', 'icon' => 'calendar', 'ability' => ['appointments.manage', 'appointments.view_own']],
                ['label' => 'التقويم', 'route' => 'appointments.calendar', 'icon' => 'calendar-days', 'ability' => ['appointments.manage', 'appointments.view_own']],
                ['label' => 'الجلسات', 'route' => 'sessions.index', 'active' => ['sessions.index', 'sessions.show'], 'icon' => 'sessions', 'ability' => ['sessions.manage', 'sessions.view_own']],
            ],
        ],
        [
            'label' => 'المرضى',
            'items' => [
                ['label' => 'ملفات المرضى', 'route' => 'patients.index', 'icon' => 'patients', 'ability' => ['patients.view', 'patients.view_own']],
                ['label' => 'إضافة مريض', 'route' => 'patients.create', 'icon' => 'patient-add', 'ability' => ['patients.create']],
            ],
        ],
        [
            'label' => 'الأطباء والمعالجون',
            'items' => [
                ['label' => 'الأطباء والمعالجون', 'route' => 'practitioners.index', 'icon' => 'practitioners', 'ability' => ['practitioners.manage']],
                ['label' => 'التوفر والدوام', 'route' => 'availability.index', 'active' => ['availability.index', 'availability.manage'], 'icon' => 'clock', 'ability' => ['practitioners.manage', 'appointments.manage']],
                ['label' => 'ملفي الشخصي', 'route' => 'practitioners.mine', 'icon' => 'practitioners', 'ability' => ['sessions.view_own'], 'roles' => ['practitioner']],
                ['label' => 'التوفر والدوام', 'route' => 'availability.mine', 'icon' => 'clock', 'ability' => ['practitioner.availability.manage_own']],
            ],
        ],
        [
            'label' => 'المالية والتقارير',
            'items' => [
                ['label' => 'المصروفات', 'route' => 'finance.expenses', 'icon' => 'expenses', 'ability' => ['expenses.manage']],
                ['label' => 'الاتفاقات المالية', 'route' => 'finance.agreements', 'icon' => 'agreements', 'ability' => ['financial.agreements.manage']],
                ['label' => 'الصناديق', 'route' => 'finance.cashboxes', 'icon' => 'cashbox', 'ability' => ['cashboxes.manage']],
                ['label' => 'الإغلاق الأسبوعي', 'route' => 'finance.weekly-closing', 'icon' => 'calendar-days', 'ability' => ['financial.dashboard.view']],
                ['label' => 'المستحقات', 'route' => 'finance.settlements', 'icon' => 'settlements', 'ability' => ['settlements.manage', 'reports.view_own']],
                ['label' => 'التقارير', 'route' => 'reports.index', 'icon' => 'reports', 'ability' => ['reports.view', 'reports.view_own']],
            ],
        ],
        [
            'label' => 'إدارة النظام',
            'items' => [
                ['label' => 'الاختصاصات', 'route' => 'master-data.specialties', 'icon' => 'specialties', 'ability' => ['master_data.manage']],
                ['label' => 'أنواع العلاج', 'route' => 'master-data.treatment-types', 'icon' => 'treatment', 'ability' => ['master_data.manage']],
                ['label' => 'الغرف', 'route' => 'master-data.rooms', 'icon' => 'rooms', 'ability' => ['master_data.manage', 'rooms.view']],
                ['label' => 'المستخدمون', 'route' => 'users.index', 'icon' => 'users', 'ability' => ['users.manage']],
                ['label' => 'سجل التدقيق', 'route' => 'finance.audit-log', 'icon' => 'audit', 'ability' => ['audit.view']],
                ['label' => 'الإعدادات', 'route' => 'settings.index', 'icon' => 'settings', 'ability' => ['settings.manage']],
            ],
        ],
    ],
];
