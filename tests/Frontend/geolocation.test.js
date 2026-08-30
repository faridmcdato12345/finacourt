import assert from 'node:assert/strict';
import test from 'node:test';
import { detectCurrentCoordinates, locationErrorMessage } from '../../resources/js/lib/geolocation.js';

test('detected coordinates are normalized for the venue form and request a fresh accurate location', async () => {
    let requestedOptions;
    const geolocation = {
        getCurrentPosition(success, _failure, options) {
            requestedOptions = options;
            success({ coords: { latitude: 7.0731, longitude: 125.6128, accuracy: 12.6 } });
        },
    };

    const result = await detectCurrentCoordinates(geolocation);

    assert.deepEqual(result, {
        latitude: '7.0731000',
        longitude: '125.6128000',
        accuracy: 13,
    });
    assert.deepEqual(requestedOptions, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0,
    });
});

test('location failures use helpful player-facing messages', async () => {
    await assert.rejects(() => detectCurrentCoordinates(null), { code: 'unsupported' });
    assert.match(locationErrorMessage({ code: 1 }), /browser settings/i);
    assert.match(locationErrorMessage({ code: 2 }), /clearer signal/i);
    assert.match(locationErrorMessage({ code: 3 }), /took too long/i);
    assert.match(locationErrorMessage({ code: 'unsupported' }), /cannot detect/i);
});
