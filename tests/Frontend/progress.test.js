import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { ProgressStore } from '../../resources/js/progress.js';

const course = { id: 'course-a' };
let store;
const summary = (revision = 0, scorm = {}) => ({ revision, scorm, sections: {}, answers: [], quiz: null });
const response = (body, status = 200) => ({ ok: status < 400, status, json: async () => body });

beforeEach(() => {
    global.window = { addEventListener() {} };
    global.document = { addEventListener() {}, querySelector: () => ({ content: 'csrf' }) };
    store = new ProgressStore();
});
afterEach(() => clearTimeout(store.timer));

test('hydrates both APIs before launch and restores suspend data and interaction count', async () => {
    global.fetch = async () => response(summary(4, { 'cmi.suspend_data': 'resume', 'cmi.interactions.2.id': 'q' }));
    await store.load([course]);
    store.install(course);
    assert.equal(window.API.LMSGetValue('cmi.suspend_data'), 'resume');
    assert.equal(window.API.LMSGetValue('cmi.core.entry'), 'resume');
    assert.equal(window.API_1484_11.GetValue('cmi.entry'), 'resume');
    assert.equal(window.API_1484_11.GetValue('cmi.interactions._count'), '3');
    assert.equal(store.read(course).revision, 4);
});

test('batches answers and commits only saved backend statistics to the view', async () => {
    global.fetch = async () => response(summary());
    await store.load([course]);
    let sent;
    global.fetch = async (url, options) => {
        sent = JSON.parse(options.body);
        assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf');
        return response({ ...summary(1, sent.runtime), completedSections: 2 });
    };
    store.set(course, 'cmi.interactions.0.id', 'q');
    store.set(course, 'cmi.interactions.0.student_response', 'A');
    assert.equal(store.read(course).completedSections, undefined);
    await store.flush();
    assert.equal(sent.runtime['cmi.interactions.0.student_response'], 'A');
    assert.equal(store.read(course).completedSections, 2);
    assert.equal(store.pending.size, 0);
});

test('writes arriving during an in-flight request use the next revision', async () => {
    global.fetch = async () => response(summary());
    await store.load([course]);
    let finish;
    const revisions = [];
    global.fetch = async (url, options) => {
        const body = JSON.parse(options.body);
        revisions.push(body.revision);
        if (revisions.length === 1) await new Promise(resolve => { finish = resolve; });
        return response(summary(body.revision + 1));
    };
    store.set(course, 'cmi.suspend_data', 'one');
    const first = store.flush();
    await new Promise(resolve => setImmediate(resolve));
    store.set(course, 'cmi.suspend_data', 'two');
    const second = store.flush();
    finish();
    await Promise.all([first, second]);
    assert.deepEqual(revisions, [0, 1]);
    assert.equal(store.get(course, 'cmi.suspend_data'), 'two');
});

test('network failures keep unsaved data and retries preserve newer answers', async () => {
    global.fetch = async () => response(summary());
    await store.load([course]);
    store.set(course, 'cmi.suspend_data', 'one');
    global.fetch = async () => { throw new Error('offline'); };
    await store.flush();
    assert.equal(store.pending.size, 1);
    assert.equal(store.read(course).revision, 0);
    store.set(course, 'cmi.suspend_data', 'two');
    global.fetch = async (url, options) => response(summary(1, JSON.parse(options.body).runtime));
    await store.flush();
    assert.equal(store.read(course).scorm['cmi.suspend_data'], 'two');
    assert.equal(store.error, null);
});

test('conflicting tabs stop writing instead of silently overwriting newer progress', async () => {
    global.fetch = async () => response(summary());
    await store.load([course]);
    store.set(course, 'cmi.suspend_data', 'stale');
    global.fetch = async () => response({ error: 'conflict' }, 409);
    await store.flush();
    assert.equal(store.error.conflict, true);
    assert.equal(store.set(course, 'cmi.suspend_data', 'another'), 'false');
    assert.equal(store.pending.size, 1);
});

test('course stores and reset are isolated and discard resume data only after success', async () => {
    const other = { id: 'course-b' };
    global.fetch = async () => response(summary(2, { 'cmi.suspend_data': 'resume' }));
    await store.load([course, other]);
    global.fetch = async () => response(summary(3));
    await store.clear(course);
    assert.equal(store.get(course, 'cmi.suspend_data'), '');
    assert.equal(store.get(course, 'cmi.core.entry'), 'ab-initio');
    assert.equal(store.get(other, 'cmi.suspend_data'), 'resume');
});
