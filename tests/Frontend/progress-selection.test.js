import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ProgressSelection } from '../../resources/js/progress-selection.js';

const courses = [{ id: 'a', title: 'Առաջին դաս' }, { id: 'b', title: 'Երկրորդ դաս' }];
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};

test('opening defaults to the first available course and reads its backend snapshot', async () => {
    const calls = [];
    const selection = new ProgressSelection(courses, async course => {
        calls.push(course.id);
        return { revision: 2, answers: ['first'] };
    });
    await selection.select('b');
    await selection.open();
    assert.equal(selection.state.course.id, 'a');
    assert.deepEqual(selection.state.progress.answers, ['first']);
    assert.deepEqual(calls, ['b', 'a']);
});

test('switching clears the old course data immediately while a fresh request is loading', async () => {
    const pending = deferred();
    const first = { revision: 3, answers: ['A'] };
    const second = { revision: 1, answers: ['B'] };
    const selection = new ProgressSelection(courses, course => course.id === 'a' ? first : pending.promise);
    await selection.open();
    const request = selection.select('b');
    assert.equal(selection.state.course.id, 'b');
    assert.equal(selection.state.progress, null);
    assert.equal(selection.state.loading, true);
    pending.resolve(second);
    await request;
    assert.strictEqual(selection.state.progress, second);
    assert.deepEqual(first.answers, ['A']);
});

test('a slower previous response cannot replace the currently selected course', async () => {
    const pending = deferred();
    const selection = new ProgressSelection(courses, course => course.id === 'a' ? pending.promise : { revision: 0, answers: ['B'] });
    const first = selection.open();
    await selection.select('b');
    pending.resolve({ revision: 9, answers: ['A'] });
    await first;
    assert.equal(selection.state.course.id, 'b');
    assert.deepEqual(selection.state.progress.answers, ['B']);
});

test('stale errors do not replace a successful selection and current errors support retry', async () => {
    const pending = deferred();
    let fail = true;
    const selection = new ProgressSelection(courses, course => {
        if (course.id === 'a') return pending.promise;
        if (fail) throw new Error('offline');
        return { revision: 1, answers: ['B'] };
    });
    const first = selection.open();
    await selection.select('b');
    assert.equal(selection.state.error.message, 'offline');
    assert.equal(selection.state.progress, null);
    fail = false;
    await selection.select('b');
    pending.reject(new Error('old request'));
    await first;
    assert.equal(selection.state.error, null);
    assert.deepEqual(selection.state.progress.answers, ['B']);
});

test('an empty course catalogue and invalid IDs do not issue requests', async () => {
    let requests = 0;
    const selection = new ProgressSelection([], () => { requests++; });
    await selection.open();
    await selection.select('not-available');
    assert.equal(requests, 0);
    assert.equal(selection.state.course, null);
    assert.equal(selection.state.progress, null);
    assert.equal(selection.state.loading, false);
});

test('updates for the playing course do not appear under a different selected course', async () => {
    const selection = new ProgressSelection(courses, async () => ({ revision: 2, answers: ['B'] }));
    await selection.select('b');
    selection.accept('a', { revision: 20, answers: ['A'] });
    assert.deepEqual(selection.state.progress.answers, ['B']);
    selection.accept('b', { revision: 1, answers: ['stale'] });
    assert.deepEqual(selection.state.progress.answers, ['B']);
    selection.accept('b', { revision: 3, answers: ['new B'] });
    assert.deepEqual(selection.state.progress.answers, ['new B']);
});

test('an acknowledged save during a pending read is not replaced by an older response', async () => {
    const pending = deferred();
    const selection = new ProgressSelection(courses, () => pending.promise);
    const load = selection.open();
    selection.accept('a', { revision: 4, answers: ['new'] });
    pending.resolve({ revision: 3, answers: ['old'] });
    await load;
    assert.equal(selection.state.progress.revision, 4);
    assert.deepEqual(selection.state.progress.answers, ['new']);
});
