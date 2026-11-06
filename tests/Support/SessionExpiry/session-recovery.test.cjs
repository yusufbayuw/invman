const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../../../public/js/livewire-session-recovery.js'), 'utf8');

function makeBrowser(initialized = true) {
    const handlers = new Map();
    const requestHooks = [];
    const notice = {
        hidden: true,
        focused: 0,
        querySelector(selector) {
            assert.equal(selector, '[data-invman-session-reload]');
            return { focus: () => { this.focused += 1; } };
        },
    };
    let reloads = 0;
    const win = { location: { reload() { reloads++; } } };
    const doc = {
        addEventListener(event, handler) {
            handlers.set(event, [...(handlers.get(event) || []), handler]);
        },
        getElementById(id) {
            return id === 'invman-session-expired' ? notice : null;
        },
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
    const run = () => vm.runInNewContext(script, { window: win, document: doc });
    const emit = (event, payload) => (handlers.get(event) || []).forEach(fn => fn(payload));
    const respond = (status) => {
        assert.equal(requestHooks.length, 1);
        let callback;
        requestHooks[0]({ fail(handler) { callback = handler; } });
        let prevented = 0;
        callback({ status, preventDefault() { prevented++; } });
        return prevented;
    };
    return { win, notice, run, emit, respond, installLivewire, requestHooks, get reloads() { return reloads; } };
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
