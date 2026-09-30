import { test, expect } from '@playwright/test';

const base = 'http://127.0.0.1:8000';

async function login(page, username) {
    await page.goto(`${base}/login`);
    await page.getByLabel('اسم المستخدم').fill(username);
    await page.locator('#password').fill('Clinic@123456');
    await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
    await expect(page).toHaveURL(/dashboard/);
}

test('secretary operational patient flow and responsive shell', async ({ page }) => {
    const errors = [];
    page.on('console', message => message.type() === 'error' && errors.push(message.text()));
    await login(page, 'secretary');
    await page.goto(`${base}/patients`);
    const addPatient = page.locator('#main-content').getByRole('link', { name: 'إضافة مريض' });
    await expect(addPatient).toBeVisible();
    await addPatient.click();
    await page.getByLabel('رقم الملف *').fill(`PW-${Date.now()}`);
    await page.getByLabel('الاسم الكامل *').fill('مريض تجريبي تشغيلي');
    await page.getByRole('button', { name: 'حفظ الملف' }).click();
    await expect(page.getByRole('heading', { name: 'مريض تجريبي تشغيلي', exact: true })).toBeVisible();
    await page.goto(`${base}/appointments/create`);
    await expect(page.getByText(/حجز موعد|موعد جديد/).first()).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}/patients`);
    await expect(page.locator('body')).not.toHaveCSS('overflow-x', 'scroll');
    await page.screenshot({ path: 'storage/app/browser-secretary-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('admin sees new operational controls in both themes', async ({ page }) => {
    const errors = [];
    page.on('console', message => message.type() === 'error' && errors.push(message.text()));
    await login(page, 'admin');
    await page.goto(`${base}/settings`);
    await expect(page.getByRole('heading', { name: 'دوام العيادة' })).toBeVisible();
    await expect(page.getByText('العملة')).toHaveCount(0);
    await page.screenshot({ path: 'storage/app/browser-settings-light.png', fullPage: true });
    await page.goto(`${base}/finance/audit-log`);
    await expect(page.getByRole('button', { name: 'تصدير Excel' })).toBeVisible();
    await page.getByRole('button', { name: 'تصدير Excel' }).click();
    await page.waitForTimeout(500);
    await page.goto(`${base}/finance/weekly-closing`);
    await expect(page.getByText(/إغلاق هذه الفترة|إعادة فتح الفترة|فترة مالية/).first()).toBeVisible();
    await page.evaluate(() => { localStorage.setItem('clinic-theme', 'dark'); document.documentElement.classList.add('dark'); });
    await page.reload();
    await page.screenshot({ path: 'storage/app/browser-finance-dark.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('practitioner demo login keeps own operational pages accessible', async ({ page }) => {
    await login(page, 'doctor1');
    await page.goto(`${base}/appointments`);
    await expect(page).toHaveURL(/appointments/);
    await page.goto(`${base}/sessions`);
    await expect(page).toHaveURL(/sessions/);
});
