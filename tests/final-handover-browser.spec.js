import { test, expect } from '@playwright/test';

const base = 'http://127.0.0.1:8000';
const password = 'Clinic@123456';

async function login(page, username) {
    await page.goto(`${base}/login`);
    await page.locator('#username').fill(username);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
    await expect(page).toHaveURL(/dashboard/);
}

async function resetLogin(page, username) {
    await page.context().clearCookies();
    await login(page, username);
}

test.describe.serial('final handover attachment and search audit', () => {
    let ownPatientUrl;
    let otherPatientUrl;
    let downloadUrl;

    test('secretary uploads image PDF and document but cannot access their contents', async ({ page }) => {
        const errors = [];
        page.on('console', message => message.type() === 'error' && errors.push(message.text()));
        await login(page, 'doctor1');
        await page.goto(`${base}/patients`);
        ownPatientUrl = await page.locator('#main-content a[href*="/patients/"]').first().getAttribute('href');

        await resetLogin(page, 'doctor2');
        await page.goto(`${base}/patients`);
        otherPatientUrl = await page.locator('#main-content a[href*="/patients/"]').first().getAttribute('href');

        await resetLogin(page, 'secretary');
        await page.goto(ownPatientUrl);
        await page.screenshot({ path: 'tests/browser-assets/handover.png' });
        await page.locator('input[type=file]').setInputFiles([
            'tests/browser-assets/handover.png',
            'tests/browser-assets/handover.pdf',
            'tests/browser-assets/handover.txt',
        ]);
        const uploadButton = page.getByRole('button', { name: 'رفع إلى التخزين الخاص' });
        await expect(uploadButton).toBeEnabled({ timeout: 30000 });
        await uploadButton.click();
        await expect(page.getByText('تم رفع المرفقات إلى الملف الآمن.')).toBeVisible();
        await expect(page.getByRole('link', { name: 'معاينة' })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'تنزيل' })).toHaveCount(0);
        await expect(page.getByText(/PNG|PDF|TXT/).first()).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('admin previews image and PDF and downloads the document', async ({ page }) => {
        await login(page, 'admin');
        await page.goto(ownPatientUrl);
        const previewLinks = page.getByRole('link', { name: 'معاينة' });
        await expect(previewLinks).toHaveCount(2);
        for (let index = 0; index < 2; index++) {
            const response = await page.request.get(await previewLinks.nth(index).getAttribute('href'));
            expect(response.status()).toBe(200);
            expect(response.headers()['x-content-type-options']).toBe('nosniff');
        }
        const downloadLink = page.getByRole('link', { name: 'تنزيل' }).last();
        downloadUrl = await downloadLink.getAttribute('href');
        const downloadResponse = await page.request.get(downloadUrl);
        expect(downloadResponse.status()).toBe(200);
        expect(downloadResponse.headers()['content-disposition']).toContain('attachment');
    });

    test('practitioner access stays scoped and appointment Arabic search works responsively', async ({ page }) => {
        const errors = [];
        page.on('console', message => message.type() === 'error' && errors.push(message.text()));
        await login(page, 'doctor1');
        await page.goto(ownPatientUrl);
        await expect(page.getByRole('link', { name: 'تنزيل' }).first()).toBeVisible();
        expect((await page.request.get(downloadUrl)).status()).toBe(200);
        await page.goto(otherPatientUrl);
        await expect(page.locator('body')).toContainText('403');

        await resetLogin(page, 'secretary');
        await page.goto(`${base}/appointments`);
        const patientName = (await page.locator('td[data-label="المريض / المرضى"] p').first().textContent()).trim();
        const fragment = patientName.slice(0, Math.max(2, Math.floor(patientName.length / 2)));
        await page.getByPlaceholder('ابحث باسم المريض...').fill(fragment);
        await expect(page.getByText(patientName, { exact: true }).first()).toBeVisible();
        await page.setViewportSize({ width: 430, height: 900 });
        await expect(page.locator('html')).toHaveJSProperty('scrollWidth', 430);
        await page.setViewportSize({ width: 390, height: 844 });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(overflow).toBe(false);
        expect(errors).toEqual([]);
    });
});
