import assert from 'node:assert/strict';
import test from 'node:test';
import {
    nearbyLocationErrorMessage,
    nearbySearchCoordinates,
    shouldAutomaticallyLocate,
} from '../../resources/js/nearby-courts.js';

test('nearby searches use neighborhood-level coordinates', () => {
    assert.deepEqual(nearbySearchCoordinates({ latitude: '14.5547123', longitude: '121.0244567' }), {
        latitude: '14.5547',
        longitude: '121.0245',
    });
    assert.equal(nearbySearchCoordinates({ latitude: 'invalid', longitude: 121 }), null);
});

test('nearby search automatically runs only after permission or an earlier opt in', () => {
    assert.equal(shouldAutomaticallyLocate({ hasCoordinates: false, permissionState: 'granted', rememberedPreference: null }), true);
    assert.equal(shouldAutomaticallyLocate({ hasCoordinates: false, permissionState: 'prompt', rememberedPreference: true }), true);
    assert.equal(shouldAutomaticallyLocate({ hasCoordinates: false, permissionState: 'granted', rememberedPreference: false }), false);
    assert.equal(shouldAutomaticallyLocate({ hasCoordinates: false, permissionState: 'prompt', rememberedPreference: null }), false);
    assert.equal(shouldAutomaticallyLocate({ hasCoordinates: true, permissionState: 'granted', rememberedPreference: true }), false);
});

test('nearby location errors give the player a useful fallback', () => {
    assert.match(nearbyLocationErrorMessage({ code: 1 }), /browser settings/i);
    assert.match(nearbyLocationErrorMessage({ code: 2 }), /location service/i);
    assert.match(nearbyLocationErrorMessage({ code: 3 }), /took too long/i);
    assert.match(nearbyLocationErrorMessage({ code: 'unsupported' }), /filter courts by city/i);
});
