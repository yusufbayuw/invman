const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../../../public/js/livewire-session-recovery.js'), 'utf8');

function makeBrowser(initialized = true) {
    const handlers = new Map();
    const requestHooks = [];
    const message = { textContent: '' };
    const login = { hidden: true };
    const heading = { textContent: '' };
    const notice = {
        hidden: true,
        focused: 0,
        dataset: { sessionUrl: 'https://invman.test/session/check', sessionUser: 'test-user' },
        querySelector(selector) {
            if (selector === '[data-invman-session-reload]') {
                return { focus: () => { this.focused += 1; } };
            }
            if (selector === '[data-invman-session-message]') return message;
            if (selector === '[data-invman-session-login]') return login;
            throw new Error('Unexpected notice selector: ' + selector);
        },
    };
    let reloads = 0;
    const win = {
        location: { href: 'https://invman.test/admin', origin: 'https://invman.test', reload() { reloads++; } },
        fetch: async () => { throw new Error('Unexpected network call in session hook test'); },
    };
    const doc = {
        addEventListener(event, handler) {
            handlers.set(event, [...(handlers.get(event) || []), handler]);
        },
        getElementById(id) {
            if (id === 'invman-session-expired') return notice;
            if (id === 'invman-session-expired-title') return heading;
            return null;
        },
        querySelectorAll: () => [],
    };
    const installLivewire = () => {
        win.Livewire = {
            hook(name, listener) {
                assert.equal(name, 'request');
                requestHooks.push(listener);
            },
        };
    };
    if (initialized) installLivewire();
    const run = () => vm.runInNewContext(script, { window: win, document: doc, Headers, Response, URL, location: win.location });
    const emit = (event, payload) => (handlers.get(event) || []).forEach(fn => fn(payload));
    const respond = (status) => {
        assert.equal(requestHooks.length, 1);
        let callback;
        requestHooks[0]({ fail(handler) { callback = handler; } });
        let prevented = 0;
        callback({ status, preventDefault() { prevented++; } });
        return prevented;
    };
    return { win, notice, heading, message, login, run, emit, respond, installLivewire, requestHooks, get reloads() { return reloads; } };
}

test('only 419 is intercepted; a notice appears once without automatic reload or retry', () => {
    const app = makeBrowser();
    app.run();
    assert.equal(app.respond(500), 0);
    assert.equal(app.notice.hidden, true);
    assert.equal(app.respond(419), 1);
    assert.equal(app.notice.hidden, false);
    assert.equal(app.notice.focused, 1);
    assert.equal(app.reloads, 0);
    assert.equal(app.respond(419), 1);
    assert.equal(app.notice.focused, 1);
    assert.equal(app.reloads, 0);
});

test('the user explicitly requests reload; repeated script execution registers one hook', () => {
    const app = makeBrowser();
    app.run();
    app.run();
    app.emit('livewire:navigated');
    assert.equal(app.requestHooks.length, 1);
    app.emit('click', { target: { closest: () => ({}) } });
    assert.equal(app.reloads, 1);
});

test('hook also registers if Livewire initializes after the recovery script', () => {
    const app = makeBrowser(false);
    app.run();
    assert.equal(app.requestHooks.length, 0);
    app.installLivewire();
    app.emit('livewire:init');
    assert.equal(app.requestHooks.length, 1);
    assert.equal(app.respond(419), 1);
});


test('Livewire POST checks the current session and refreshes its CSRF token before sending a write', async () => {
    const app = makeBrowser();
    const calls = [];
    app.win.fetch = async (input, options) => {
        calls.push({ input, options });
        if (calls.length === 1) {
            assert.equal(input, app.notice.dataset.sessionUrl);
            assert.equal(options.method, undefined);
            return new Response(JSON.stringify({ user: 'test-user', token: 'fresh-token' }), { status: 200 });
        }
        return new Response('{}', { status: 200 });
    };
    app.run();
    const response = await app.win.fetch('https://invman.test/livewire/update', {
        method: 'POST',
        headers: { 'X-Livewire': 'true', 'X-CSRF-TOKEN': 'stale-token' },
        body: JSON.stringify({ _token: 'stale-token', updates: [{ type: 'syncInput' }] }),
    });
    assert.equal(response.status, 200);
    assert.equal(calls.length, 2);
    assert.equal(calls[1].options.headers.get('X-CSRF-TOKEN'), 'fresh-token');
    assert.equal(JSON.parse(calls[1].options.body)._token, 'fresh-token');
    assert.equal(app.reloads, 0);
});

test('unmarked 419 is never retried, preventing accidental duplicate writes', async () => {
    const app = makeBrowser();
    let requests = 0;
    app.win.fetch = async () => {
        requests++;
        return requests === 1
            ? new Response(JSON.stringify({ user: 'test-user', token: 'current-token' }), { status: 200 })
            : new Response('{}', { status: 419 });
    };
    app.run();
    const response = await app.win.fetch('https://invman.test/livewire/update', {
        method: 'POST', headers: { 'X-Livewire': 'true' }, body: JSON.stringify({ _token: 'old' }),
    });
    assert.equal(response.status, 419);
    assert.equal(requests, 2);
});

test('only a marked CSRF rejection with a rotated token is retried once', async () => {
    const app = makeBrowser();
    const requests = [];
    app.win.fetch = async (input, options) => {
        requests.push({ input, options });
        switch (requests.length) {
            case 1: return new Response(JSON.stringify({ user: 'test-user', token: 'first-token' }), { status: 200 });
            case 2: return new Response('{}', { status: 419, headers: { 'X-Invman-CSRF-Rejected': '1' } });
            case 3: return new Response(JSON.stringify({ user: 'test-user', token: 'second-token' }), { status: 200 });
            case 4: return new Response('{}', { status: 200 });
            default: throw new Error('Unexpected extra retry');
        }
    };
    app.run();
    const response = await app.win.fetch('https://invman.test/livewire/update', {
        method: 'POST', headers: { 'X-Livewire': 'true' }, body: JSON.stringify({ _token: 'stale' }),
    });
    assert.equal(response.status, 200);
    assert.equal(requests.length, 4);
    assert.equal(JSON.parse(requests[1].options.body)._token, 'first-token');
    assert.equal(JSON.parse(requests[3].options.body)._token, 'second-token');
});

test('a changed account blocks Livewire writes before reaching the server', async () => {
    const app = makeBrowser();
    let requests = 0;
    app.win.fetch = async () => {
        requests++;
        return new Response(JSON.stringify({ user: 'different-user', token: 'token' }), { status: 200 });
    };
    app.run();
    const response = await app.win.fetch('https://invman.test/livewire/update', {
        method: 'POST', headers: { 'X-Livewire': 'true' }, body: JSON.stringify({ _token: 'stale' }),
    });
    assert.equal(response.status, 401);
    assert.equal((await response.json()).invman_session_issue, 'account-changed');
    assert.equal(requests, 1);
});
