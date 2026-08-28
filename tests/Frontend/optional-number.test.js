import assert from 'node:assert/strict';
import test from 'node:test';
import { optionalFiniteNumber } from '../../resources/js/lib/numbers.js';

test('optional numeric bounds remain absent instead of becoming zero', () => {
    assert.equal(optionalFiniteNumber(null), undefined);
    assert.equal(optionalFiniteNumber(undefined), undefined);
    assert.equal(optionalFiniteNumber(''), undefined);
});

test('valid numeric bounds preserve zero and positive values', () => {
    assert.equal(optionalFiniteNumber(0), 0);
    assert.equal(optionalFiniteNumber('0'), 0);
    assert.equal(optionalFiniteNumber('500.25'), 500.25);
});
