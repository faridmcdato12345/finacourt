import assert from 'node:assert/strict';
import test from 'node:test';
import {
    groupOpportunities,
    primaryOpportunitySignal,
    relativeUpdatedAt,
} from '../../resources/js/lib/growth-opportunities.js';

test('urgent open inventory leads with the next 24 hours instead of the bounded scan total', () => {
    const signal = primaryOpportunitySignal({
        type: 'empty_inventory',
        evidence: {
            empty_slot_count: 250,
            last_minute_slot_count: 44,
            horizon_days: 7,
            scan_capped: true,
        },
    });

    assert.deepEqual(signal, {
        value: '44',
        label: 'open court times in the next 24 hours',
    });
});

test('remaining recommendations are grouped by an owner business goal', () => {
    const groups = groupOpportunities([
        { key: 'inventory', type: 'empty_inventory' },
        { key: 'customers', type: 'inactive_customers' },
        { key: 'visibility', type: 'low_booking_conversion' },
        { key: 'campaign', type: 'repeat_successful_campaign' },
    ]);

    assert.deepEqual(groups.map((group) => group.key), [
        'fill-open-times',
        'bring-players-back',
        'improve-visibility',
        'repeat-promotions',
    ]);
    assert.equal(groups[0].recommendations[0].key, 'inventory');
});

test('new recommendation types remain visible in a safe fallback group', () => {
    const groups = groupOpportunities([{ key: 'future', type: 'future_growth_rule' }]);

    assert.equal(groups[0].key, 'other-opportunities');
    assert.equal(groups[0].recommendations[0].key, 'future');
});

test('updated timestamps use concise relative business language', () => {
    const now = Date.parse('2026-09-03T08:10:00Z');

    assert.equal(relativeUpdatedAt('2026-09-03T08:09:35Z', now), 'just now');
    assert.equal(relativeUpdatedAt('2026-09-03T08:05:00Z', now), '5 min ago');
    assert.equal(relativeUpdatedAt('2026-09-02T08:10:00Z', now), '1 day ago');
});
