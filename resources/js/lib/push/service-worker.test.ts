import assert from 'node:assert/strict';
import test from 'node:test';
import {
    hasFirebaseWebWorkerConfig,
    messagingServiceWorkerUrl,
    SHARED_SERVICE_WORKER_SCOPE,
    SHARED_SERVICE_WORKER_SCRIPT,
} from './service-worker.ts';

const config = {
    apiKey: 'public-api-key',
    authDomain: 'chisdrive.firebaseapp.com',
    projectId: 'chisdrive',
    storageBucket: 'chisdrive.appspot.com',
    messagingSenderId: '123',
    appId: '1:123:web:abc',
};

test('service worker url carries the public firebase config and scope is root', () => {
    const url = new URL(messagingServiceWorkerUrl(config), 'https://driver.chisdrive.com');

    assert.equal(url.pathname, SHARED_SERVICE_WORKER_SCRIPT);
    assert.equal(url.pathname.includes('firebase-messaging-sw.js'), false);
    assert.equal(SHARED_SERVICE_WORKER_SCOPE, '/');
    assert.equal(url.searchParams.get('apiKey'), config.apiKey);
    assert.equal(url.searchParams.get('projectId'), config.projectId);
    assert.equal(url.searchParams.get('appId'), config.appId);
    assert.equal(url.searchParams.get('messagingSenderId'), config.messagingSenderId);
    assert.equal(hasFirebaseWebWorkerConfig(config), true);
    assert.equal(
        hasFirebaseWebWorkerConfig({ ...config, apiKey: '' }),
        false,
    );
});
