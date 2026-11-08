const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

async function scenario(user, responseUser, status = 200) {
    const calls = [];
    const panel = { dataset: { sessionUrl: '/session/status', sessionUser: user } };
    const window = {
        fetch: async (url, options) => {
            calls.push({ url, options });
            return url === '/session/status'
                ? new Response(JSON.stringify({ user: responseUser, token: 'fresh-token' }), { status })
                : new Response('ok');
        },
    };
    vm.runInNewContext(readFileSync('public/js/livewire-session-recovery.js', 'utf8'), {
        window, document: { getElementById: () => panel, addEventListener() {} },
        Headers, Response, URL, location: { href: 'https://invman.test/admin', origin: 'https://invman.test' },
    });
    const response = await window.fetch('/livewire/update', {
        method: 'POST', headers: { 'X-Livewire': '', 'Content-Type': 'application/json' },
        body: JSON.stringify({ _token: 'stale-token', components: [{ calls: ['save'] }] }),
    });
    return { calls, response, window };
}

(async () => {
    for (const user of ['', '42']) {
        const { calls, response } = await scenario(user, user);
        assert.equal(response.status, 200);
        assert.equal(calls.length, 2);
        const payload = JSON.parse(calls[1].options.body);
        assert.equal(payload._token, 'fresh-token');
        assert.deepEqual(payload.components, [{ calls: ['save'] }]);
    }
    for (const nextUser of ['', '43']) {
        const { calls, response } = await scenario('42', nextUser);
        assert.equal(response.status, 419);
        assert.equal(calls.length, 1, 'Expired/changed account must never receive a write');
    }
    const { calls, response, window } = await scenario('42', '42', 403);
    assert.equal(response.status, 419);
    assert.equal(calls.length, 1);
    await window.fetch('/other', { method: 'POST', body: 'untouched' });
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.body, 'untouched');
    console.log('Session preflight: 6 scenarios passed');
})().catch((error) => { console.error(error); process.exitCode = 1; });
