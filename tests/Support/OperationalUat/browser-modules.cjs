const paceLogin = require('./login-pace.cjs');
const { chromium } = require('../../../output/operational-uat-runtime/browser/node_modules/playwright');
const fs = require('node:fs/promises');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../../..');
const out = path.join(root, 'docs/operational-readiness/evidence/2026-11-04');
const base = 'http://127.0.0.1:8876';
const settings = '/admin/manajemen-modul';
const keys = ['loans', 'tickets', 'work_orders', 'public_schedule', 'reports', 'chat'];
const results = [];

async function login(browser, username, password) {
    await paceLogin();
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    await page.goto(base + '/admin/login', { waitUntil: 'domcontentloaded' });
    await page.locator('input[id="data.email"]').fill(username);
    await page.locator('input[type=password]').fill(password);
    await page.waitForFunction(() => !!document.querySelector('input[name="cf-turnstile-response"]')?.value, {}, { timeout: 30000 });
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL(url => url.pathname === '/admin', { timeout: 45000 });
    return { context, page };
}

async function open(page, url, status) {
    const response = await page.goto(base + url, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), status, url + ' unexpected HTTP status');
}

function toggle(page, key) {
    return page.locator('button[role="switch"][id="data.' + key + '"]');
}

async function readState(page, key) {
    await toggle(page, key).waitFor();
    const state = await toggle(page, key).getAttribute('aria-checked');
    assert.ok(['true', 'false'].includes(state), 'Missing checked state for ' + key);
    return state === 'true';
}

async function dismissOnboarding(page) {
    const close = page.locator('#pwa-onboarding [data-pwa-dismiss]');
    if (await close.isVisible()) {
        await close.click();
        await page.locator('#pwa-onboarding').waitFor({ state: 'hidden' });
    }
}

async function verifyOnboardingDoesNotObstructSave(page) {
    const banner = page.locator('#pwa-onboarding');
    await banner.waitFor({ state: 'attached' });
    await page.waitForFunction(() => window.PpasPwa
        && document.querySelector('#pwa-onboarding [data-pwa-status]')?.textContent !== 'Memeriksa dukungan perangkat…');
    const sizes = [];
    for (const width of [1366, 390]) {
        await page.setViewportSize({ width, height: 900 });
        const overlay = page.locator('.fi-sidebar-close-overlay');
        if (width < 1024 && await overlay.isVisible()) {
            const box = await overlay.boundingBox();
            await overlay.click({ position: { x: box.width - 12, y: Math.min(300, box.height / 2) } });
            await overlay.waitFor({ state: 'hidden' });
        }
        const layout = await banner.evaluate(element => ({
            position: getComputedStyle(element).position,
            hidden: element.hidden,
            permission: typeof Notification === 'undefined' ? 'unsupported' : Notification.permission,
            installAvailable: !element.querySelector('[data-pwa-install-button]').hidden,
            width: innerWidth,
            documentWidth: document.documentElement.scrollWidth,
        }));
        assert.ok(['relative', 'static'].includes(layout.position), 'Onboarding must be in document flow');
        assert.ok(layout.documentWidth <= width + 1, 'Horizontal overflow on module management: ' + JSON.stringify(layout));
        if (layout.permission === 'denied' && !layout.installAvailable) {
            assert.equal(layout.hidden, true, 'Blocked notifications without install action must not prompt');
        }
        // Real actionability check, including pointer hit-testing. No force or DOM click.
        await page.getByRole('button', { name: 'Simpan Pengaturan', exact: true }).click({ trial: true });
        sizes.push(layout);
    }
    await page.setViewportSize({ width: 1366, height: 900 });
    await dismissOnboarding(page);
    return sizes;
}

async function saveState(page, states) {
    await open(page, settings, 200);
    await dismissOnboarding(page);
    for (const [key, wanted] of Object.entries(states)) {
        if ((await readState(page, key)) !== wanted) {
            await toggle(page, key).click();
        }
    }
    await page.getByRole('button', { name: 'Simpan Pengaturan', exact: true }).click();
    await page.getByText('Pengaturan modul disimpan', { exact: true }).waitFor({ timeout: 30000 });
    await open(page, settings, 200);
    for (const [key, wanted] of Object.entries(states)) {
        assert.equal(await readState(page, key), wanted, 'Saved toggle did not persist: ' + key);
    }
}

(async () => {
    const manifest = JSON.parse(await fs.readFile(path.join(out, 'fixtures.json'), 'utf8'));
    const env = await fs.readFile(path.join(root, 'output/operational-uat-runtime/app/.env'), 'utf8');
    const password = env.match(/^SEEDED_USER_PASSWORD="([^"]+)"/m)?.[1];
    assert.ok(password, 'Missing synthetic password');
    const browser = await chromium.launch({ headless: true });
    let admin, before;
    try {
        admin = await login(browser, 'admin', password);
        await open(admin.page, settings, 200);
        const layout = await verifyOnboardingDoesNotObstructSave(admin.page);
        results.push({ scenario: 'pwa-onboarding-non-obstructing', status: 'PASS', layouts: layout });
        before = {};
        for (const key of keys) before[key] = await readState(admin.page, key);
        await saveState(admin.page, Object.fromEntries(keys.map(key => [key, false])));
        await admin.page.screenshot({ path: path.join(out, 'modules-disabled.png'), fullPage: true });
        await open(admin.page, '/admin/ajukan-peminjaman', 403);
        await open(admin.page, '/admin/tiket/create', 403);
        await open(admin.page, '/admin/chat', 403);
        results.push({ scenario: 'super-admin-save-and-direct-url', status: 'PASS' });

        const guest = await browser.newContext();
        try {
            const page = await guest.newPage();
            await open(page, '/', 200);
            await page.getByText('Masuk untuk menggunakan layanan aplikasi.', { exact: true }).waitFor();
            assert.equal(await page.getByText('Ringkasan Jadwal', { exact: true }).count(), 0);
            results.push({ scenario: 'guest-public-schedule-disabled', status: 'PASS' });
        } finally { await guest.close(); }

        // Use the synthetic persona mapping, not hardcoded role permissions.
        for (const username of new Set(Object.values(manifest.persona_mapping))) {
            if (username === 'admin') continue;
            const session = await login(browser, username, password);
            try {
                await open(session.page, settings, 403);
                await open(session.page, '/admin/ajukan-peminjaman', 403);
                await open(session.page, '/admin/tiket/create', 403);
                results.push({ scenario: 'module-boundaries', role: username, status: 'PASS' });
            } finally { await session.context.close(); }
        }
        await saveState(admin.page, before);
        await admin.page.screenshot({ path: path.join(out, 'modules-restored.png'), fullPage: true });
        results.push({ scenario: 'reactivation-and-restore', status: 'PASS' });
        before = null; // Successfully restored; no duplicate save in finally.
    } catch (error) {
        results.push({ scenario: 'module-uat', status: 'FAIL', error: String(error.message).replaceAll(password, '[REDACTED]') });
        process.exitCode = 1;
        if (admin) await admin.page.screenshot({ path: path.join(out, 'modules-error.png'), fullPage: true, timeout: 10000 }).catch(() => {});
    } finally {
        if (admin && before) {
            try { await saveState(admin.page, before); }
            catch (error) {
                results.push({ scenario: 'restore-original-settings', status: 'FAIL', error: 'Original module settings could not be restored through UI' });
                process.exitCode = 1;
            }
        }
        await browser.close();
        await fs.writeFile(path.join(out, 'browser-modules.json'), JSON.stringify({ results, pass: !process.exitCode }, null, 2));
        console.log(JSON.stringify({ results, pass: !process.exitCode }));
    }
})().catch(error => { console.error('Module UAT setup failed: ' + error.message); process.exit(1); });
