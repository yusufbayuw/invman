const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = readFileSync('public/js/livewire-session-recovery.js', 'utf8');

function setup({ user = '42', sessions = [{ user: '42', token: 'token-a' }], postResponses = [new Response('ok')] } = {}) {
    const calls = [];
    let statusCalls = 0, postCalls = 0;
    const tokenSources = { meta: 'old-token', script: 'old-token' };
    const panel = { dataset: { sessionUrl: '/session/status', sessionUser: user } };
    const window = { livewireScriptConfig: { csrf: 'old-token' }, fetch: async (url, options) => {
        calls.push({ url, options });
        const result = url === '/session/status' ? sessions[Math.min(statusCalls++, sessions.length - 1)] : postResponses[Math.min(postCalls++, postResponses.length - 1)];
        if (result instanceof Error) throw result;
        return result instanceof Response ? result : new Response(JSON.stringify(result));
    } };
    vm.runInNewContext(source, { window, document: { getElementById: () => panel, addEventListener() {}, querySelectorAll: (selector) => [{ setAttribute: (name, value) => { tokenSources[selector.startsWith('meta') ? 'meta' : 'script'] = value; } }] },
        Headers, Response, URL, location: { href: 'https://invman.test/admin', origin: 'https://invman.test' } });
    const send = () => window.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '', 'Content-Type': 'application/json' },
        body: JSON.stringify({ _token: 'old-token', components: [{ calls: [{ method: 'save', params: [7] }] }] }) });
    return { calls, send, window, tokenSources };
}

(async () => {
    let passed = 0;
    for (const user of ['', '42']) {
        const { calls, send, window, tokenSources } = setup({ user, sessions: [{ user, token: 'fresh-token' }] });
        assert.equal((await send()).status, 200);
        assert.equal(calls.length, 2);
        const payload = JSON.parse(calls[1].options.body);
        assert.equal(payload._token, 'fresh-token');
        assert.equal(tokenSources.meta, 'fresh-token');
        assert.equal(tokenSources.script, 'fresh-token');
        assert.equal(window.livewireScriptConfig.csrf, 'fresh-token');
        assert.deepEqual(payload.components, [{ calls: [{ method: 'save', params: [7] }] }]);
        passed++;
    }
    for (const user of ['', '43']) {
        const { calls, send } = setup({ sessions: [{ user, token: 'new-token' }] });
        const response = await send();
        assert.equal(response.status, 401, 'Identity changes are not CSRF rejections');
        assert.equal(calls.length, 1, 'Do not write under another/expired identity');
        assert.equal((await response.json()).invman_session_issue, user ? 'account-changed' : 'expired');
        passed++;
    }
    for (const status of [401, 403, 423, 500]) {
        const { calls, send } = setup({ sessions: [new Response('{}', { status })] });
        assert.equal((await send()).status, status, 'Never relabel server errors as 419');
        assert.equal(calls.length, 1);
        passed++;
    }
    const marked = () => new Response('{}', { status: 419, headers: { 'X-Invman-CSRF-Rejected': '1' } });
    {
        const { calls, send } = setup({ sessions: [{ user: '42', token: 'a' }, { user: '42', token: 'b' }], postResponses: [marked(), new Response('ok')] });
        assert.equal((await send()).status, 200);
        assert.equal(calls.length, 4);
        assert.equal(JSON.parse(calls[3].options.body)._token, 'b');
        assert.deepEqual(JSON.parse(calls[1].options.body).components, JSON.parse(calls[3].options.body).components);
        passed++;
    }
    for (const rejection of [new Response('{}', { status: 419 }), new Response('{}', { status: 500 }), new Error('network lost')]) {
        const { calls, send } = setup({ postResponses: [rejection] });
        if (rejection instanceof Error) await assert.rejects(send, /network lost/); else assert.equal((await send()).status, rejection.status);
        assert.equal(calls.length, 2, 'An operation with unknown execution status must never be replayed');
        passed++;
    }
    {
        const { calls, send } = setup({ postResponses: [marked()] });
        assert.equal((await send()).status, 419);
        assert.equal(calls.length, 3, 'Same token cannot help; no repeated write');
        passed++;
    }
    {
        const { calls, send } = setup({ sessions: [{ user: '42', token: 'a' }, { user: '43', token: 'b' }], postResponses: [marked()] });
        assert.equal((await send()).status, 401);
        assert.equal(calls.length, 3, 'Never replay under a newly signed-in identity');
        passed++;
    }
    {
        const { calls, send, window } = setup({ sessions: [new Error('offline')] });
        assert.equal((await send()).status, 503);
        assert.equal(calls.length, 1);
        await window.fetch('/other', { method: 'POST', body: 'unchanged' });
        assert.equal(calls[1].options.body, 'unchanged');
        passed++;
    }
    {
        let hook, failHandler, prevented = 0, focused = 0;
        const heading = { textContent: '' }, paragraph = { textContent: '' }, login = { hidden: true };
        const panel = { hidden: true, querySelector: selector => selector.includes('message') ? paragraph : selector.includes('login') ? login : { focus() { focused++; } } };
        const window = { fetch: async () => new Response('ok'), Livewire: { hook: (_name, callback) => { hook = callback; } } };
        vm.runInNewContext(source, { window, document: { getElementById: id => id.endsWith('-title') ? heading : panel, addEventListener() {} },
            Headers, Response, URL, location: { href: 'https://invman.test/admin', origin: 'https://invman.test' } });
        hook({ fail: callback => { failHandler = callback; } });
        const fail = (status, issue) => failHandler({ status, content: issue ? JSON.stringify({ invman_session_issue: issue }) : '{}', preventDefault() { prevented++; } });
        fail(500, 'check-failed');
        assert.match(heading.textContent, /Pemeriksaan sesi/);
        assert.match(paragraph.textContent, /bukan bukti/);
        fail(401, 'expired');
        assert.equal(login.hidden, false);
        assert.match(heading.textContent, /berakhir/);
        fail(419);
        assert.match(paragraph.textContent, /419/);
        assert.doesNotMatch(paragraph.textContent, /belum dijalankan/, 'Unmarked 419 does not prove execution status');
        fail(419, 'component-release');
        assert.match(paragraph.textContent, /Komponen halaman tidak dikenali/);
        assert.equal(login.hidden, true, 'Component registration errors must not ask for another login');
        fail(422);
        assert.equal(prevented, 4, 'Do not intercept ordinary form validation');
        assert.equal(focused, 1, 'Background polling must not keep stealing focus');
        passed++;
    }
    for (const target of ['https://example.test/livewire/update', new URL('https://example.test/livewire/update')]) {
        const { calls, window } = setup();
        const body = JSON.stringify({ _token: 'original-token' });
        await window.fetch(target, { method: 'POST', headers: { 'X-Livewire': '' }, body });
        assert.equal(calls.length, 1, 'Cross-origin requests must bypass local session checking');
        assert.equal(calls[0].options.body, body, 'Never inject this application token into another origin');
        passed++;
    }
    console.log(`Session recovery: ${passed} scenarios passed`);
})().catch(error => { console.error(error); process.exitCode = 1; });
