import assert from 'node:assert/strict';
import test from 'node:test';
import { normalizeMapCoordinates } from '../../resources/js/lib/venue-map.js';

test('map clicks and marker drags produce precise form values', () => {
    assert.deepEqual(normalizeMapCoordinates(8.01286991, 124.28674484), {
        latitude: 8.01286991,
        longitude: 124.28674484,
        latitudeValue: '8.0128699',
        longitudeValue: '124.2867448',
    });
});

test('map coordinates reject incomplete, invalid, and out-of-range positions', () => {
    assert.equal(normalizeMapCoordinates('', 124.2), null);
    assert.equal(normalizeMapCoordinates(8.1, ''), null);
    assert.equal(normalizeMapCoordinates('north', 124.2), null);
    assert.equal(normalizeMapCoordinates(91, 124.2), null);
    assert.equal(normalizeMapCoordinates(8.1, 181), null);
});
