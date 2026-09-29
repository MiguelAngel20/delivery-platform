import assert from 'node:assert/strict';
import test from 'node:test';
import { releasePushTokenBeforeLogout } from './release-push-token.ts';

test('logout waits for token release', async () => {
    const order: string[] = [];

    await releasePushTokenBeforeLogout(async () => {
        order.push('release');
    });
    order.push('logout');

    assert.deepEqual(order, ['release', 'logout']);
});

test('a failed token release still lets logout continue', async () => {
    let loggedOut = false;

    await releasePushTokenBeforeLogout(async () => {
        throw new Error('network');
    });
    loggedOut = true;

    assert.equal(loggedOut, true);
});
