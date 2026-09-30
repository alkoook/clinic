# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests\final-handover-browser.spec.js >> final handover attachment and search audit >> secretary uploads image PDF and document but cannot access their contents
- Location: tests\final-handover-browser.spec.js:24:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByText('تم رفع المرفقات إلى الملف الآمن.')
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByText('تم رفع المرفقات إلى الملف الآمن.') with timeout 5000ms
  - waiting for getByText('تم رفع المرفقات إلى الملف الآمن.')

```

```yaml
- link "الانتقال إلى المحتوى":
  - /url: "#main-content"
- complementary:
  - link "عيادة الحياة":
    - /url: http://127.0.0.1:8000/dashboard
  - navigation "التنقل الرئيسي":
    - heading "التشغيل اليومي" [level=2]
    - link "لوحة التشغيل":
      - /url: http://127.0.0.1:8000/dashboard
    - link "المواعيد":
      - /url: http://127.0.0.1:8000/appointments
    - link "التقويم":
      - /url: http://127.0.0.1:8000/appointments/calendar
    - link "الجلسات":
      - /url: http://127.0.0.1:8000/sessions
    - heading "المرضى" [level=2]
    - link "ملفات المرضى":
      - /url: http://127.0.0.1:8000/patients
    - link "إضافة مريض":
      - /url: http://127.0.0.1:8000/patients/create
    - heading "الأطباء والمعالجون" [level=2]
    - link "التوفر والدوام":
      - /url: http://127.0.0.1:8000/availability/practitioners
    - heading "المالية والتقارير" [level=2]
    - link "المصروفات":
      - /url: http://127.0.0.1:8000/finance/expenses
    - heading "إدارة النظام" [level=2]
    - link "الغرف":
      - /url: http://127.0.0.1:8000/master-data/rooms
  - button "تسجيل الخروج"
  - paragraph: بيانات المرضى سرية — استخدمها ضمن صلاحياتك فقط.
- banner:
  - button "طي القائمة الجانبية"
  - paragraph: مساحة العمل الآمنة
  - heading "ملف ليان العلي" [level=1]
  - button "الإشعارات": 99+
  - button "تفعيل المظهر الداكن"
  - text: ل
  - paragraph: لينا منصور — السكرتارية
  - paragraph: السكرتارية
- main:
  - paragraph: ملف رقم CL-00073
  - heading "ليان العلي" [level=1]
  - paragraph: 0933009864 · من طرف العيادة
  - link "تعديل الملف":
    - /url: http://127.0.0.1:8000/patients/73/edit
  - link "المرضى":
    - /url: http://127.0.0.1:8000/patients
  - article:
    - heading "بيانات المريض" [level=2]
    - term: المرافق
    - definition: —
    - term: المستشفى المحوّل منه
    - definition: مشفى الرازي
    - term: المعالج المحوّل
    - definition: —
    - term: المعالج الحالي
    - definition: د. محمد خالد
    - term: الجهة المحوّل إليها
    - definition: —
    - term: تاريخ التحويل الخارجي
    - definition: —
  - article:
    - heading "ملاحظات" [level=2]
    - paragraph: يفضّل المواعيد المسائية.
  - heading "المواعيد" [level=2]
  - table:
    - rowgroup:
      - row "التاريخ المعالج الغرفة الخدمة الحالة":
        - columnheader "التاريخ"
        - columnheader "المعالج"
        - columnheader "الغرفة"
        - columnheader "الخدمة"
        - columnheader "الحالة"
    - rowgroup:
      - row "2026/09/17 11:00 د. هالة النجار غرفة 3 — الجلسات الجماعية والأسرية جلسة علاج نفسي فردي لم يحضر":
        - cell "2026/09/17 11:00"
        - cell "د. هالة النجار"
        - cell "غرفة 3 — الجلسات الجماعية والأسرية"
        - cell "جلسة علاج نفسي فردي"
        - cell "لم يحضر"
  - heading "الجلسات والمدفوعات" [level=2]
  - table:
    - rowgroup:
      - row "الجلسة التاريخ الإجمالي الخصم حالة الدفع":
        - columnheader "الجلسة"
        - columnheader "التاريخ"
        - columnheader "الإجمالي"
        - columnheader "الخصم"
        - columnheader "حالة الدفع"
    - rowgroup:
      - row "لم تُنشأ جلسات لهذا المريض.":
        - cell "لم تُنشأ جلسات لهذا المريض."
  - heading "الملفات والمرفقات" [level=2]
  - text: لا توجد مرفقات.
  - heading "رفع مرفق" [level=2]
  - text: العنوان
  - textbox "العنوان"
  - text: ربط بجلسة (اختياري)
  - combobox "ربط بجلسة (اختياري)" [disabled]:
    - option "دون ربط" [selected]
  - text: الملفات (حتى 25 م.ب للملف)
  - button "الملفات (حتى 25 م.ب للملف)" [disabled]
  - text: الوصف
  - textbox "الوصف"
  - button "رفع إلى التخزين الخاص" [disabled]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | const base = 'http://127.0.0.1:8000';
  4  | const password = 'Clinic@123456';
  5  | 
  6  | async function login(page, username) {
  7  |     await page.goto(`${base}/login`);
  8  |     await page.locator('#username').fill(username);
  9  |     await page.locator('#password').fill(password);
  10 |     await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
  11 |     await expect(page).toHaveURL(/dashboard/);
  12 | }
  13 | 
  14 | async function resetLogin(page, username) {
  15 |     await page.context().clearCookies();
  16 |     await login(page, username);
  17 | }
  18 | 
  19 | test.describe.serial('final handover attachment and search audit', () => {
  20 |     let ownPatientUrl;
  21 |     let otherPatientUrl;
  22 |     let downloadUrl;
  23 | 
  24 |     test('secretary uploads image PDF and document but cannot access their contents', async ({ page }) => {
  25 |         const errors = [];
  26 |         page.on('console', message => message.type() === 'error' && errors.push(message.text()));
  27 |         await login(page, 'doctor1');
  28 |         await page.goto(`${base}/patients`);
  29 |         ownPatientUrl = await page.locator('#main-content a[href*="/patients/"]').first().getAttribute('href');
  30 | 
  31 |         await resetLogin(page, 'doctor2');
  32 |         await page.goto(`${base}/patients`);
  33 |         otherPatientUrl = await page.locator('#main-content a[href*="/patients/"]').first().getAttribute('href');
  34 | 
  35 |         await resetLogin(page, 'secretary');
  36 |         await page.goto(ownPatientUrl);
  37 |         await page.screenshot({ path: 'tests/browser-assets/handover.png' });
  38 |         await page.locator('input[type=file]').setInputFiles([
  39 |             'tests/browser-assets/handover.png',
  40 |             'tests/browser-assets/handover.pdf',
  41 |             'tests/browser-assets/handover.txt',
  42 |         ]);
  43 |         await page.getByRole('button', { name: 'رفع إلى التخزين الخاص' }).click();
> 44 |         await expect(page.getByText('تم رفع المرفقات إلى الملف الآمن.')).toBeVisible();
     |                                                                          ^ Error: expect(locator).toBeVisible() failed
  45 |         await expect(page.getByRole('link', { name: 'معاينة' })).toHaveCount(0);
  46 |         await expect(page.getByRole('link', { name: 'تنزيل' })).toHaveCount(0);
  47 |         await expect(page.getByText(/PNG|PDF|TXT/).first()).toBeVisible();
  48 |         expect(errors).toEqual([]);
  49 |     });
  50 | 
  51 |     test('admin previews image and PDF and downloads the document', async ({ page }) => {
  52 |         await login(page, 'admin');
  53 |         await page.goto(ownPatientUrl);
  54 |         const previewLinks = page.getByRole('link', { name: 'معاينة' });
  55 |         await expect(previewLinks).toHaveCount(2);
  56 |         for (let index = 0; index < 2; index++) {
  57 |             const response = await page.request.get(await previewLinks.nth(index).getAttribute('href'));
  58 |             expect(response.status()).toBe(200);
  59 |             expect(response.headers()['x-content-type-options']).toBe('nosniff');
  60 |         }
  61 |         const downloadLink = page.getByRole('link', { name: 'تنزيل' }).last();
  62 |         downloadUrl = await downloadLink.getAttribute('href');
  63 |         const downloadResponse = await page.request.get(downloadUrl);
  64 |         expect(downloadResponse.status()).toBe(200);
  65 |         expect(downloadResponse.headers()['content-disposition']).toContain('attachment');
  66 |     });
  67 | 
  68 |     test('practitioner access stays scoped and appointment Arabic search works responsively', async ({ page }) => {
  69 |         const errors = [];
  70 |         page.on('console', message => message.type() === 'error' && errors.push(message.text()));
  71 |         await login(page, 'doctor1');
  72 |         await page.goto(ownPatientUrl);
  73 |         await expect(page.getByRole('link', { name: 'تنزيل' }).first()).toBeVisible();
  74 |         expect((await page.request.get(downloadUrl)).status()).toBe(200);
  75 |         await page.goto(otherPatientUrl);
  76 |         await expect(page.locator('body')).toContainText('403');
  77 | 
  78 |         await resetLogin(page, 'secretary');
  79 |         await page.goto(`${base}/appointments`);
  80 |         const patientName = (await page.locator('td[data-label="المريض / المرضى"] p').first().textContent()).trim();
  81 |         const fragment = patientName.slice(0, Math.max(2, Math.floor(patientName.length / 2)));
  82 |         await page.getByPlaceholder('ابحث باسم المريض...').fill(fragment);
  83 |         await expect(page.getByText(patientName, { exact: true }).first()).toBeVisible();
  84 |         await page.setViewportSize({ width: 430, height: 900 });
  85 |         await expect(page.locator('html')).toHaveJSProperty('scrollWidth', 430);
  86 |         await page.setViewportSize({ width: 390, height: 844 });
  87 |         const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  88 |         expect(overflow).toBe(false);
  89 |         expect(errors).toEqual([]);
  90 |     });
  91 | });
  92 | 
```