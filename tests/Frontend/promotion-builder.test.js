import assert from 'node:assert/strict';
import test from 'node:test';
import {
    normalizePromotionPayload,
    PROMOTION_STRATEGY,
    promotionPricePreview,
    strategyForPromotionType,
} from '../../resources/js/lib/promotion-builder.js';

test('promotion types map to three owner-friendly strategies', () => {
    assert.equal(strategyForPromotionType('specific_slots'), PROMOTION_STRATEGY.EXACT);
    assert.equal(strategyForPromotionType('time_window'), PROMOTION_STRATEGY.RECURRING);
    assert.equal(strategyForPromotionType('venue'), PROMOTION_STRATEGY.SCOPE);
    assert.equal(strategyForPromotionType('resource'), PROMOTION_STRATEGY.SCOPE);
    assert.equal(strategyForPromotionType('deal'), PROMOTION_STRATEGY.SCOPE);
});

test('exact court times are authoritative and derive their campaign date range', () => {
    const normalized = normalizePromotionPayload({
        promotion_type: 'deal',
        goal: 'fill_empty_slots',
        resource_id: 9,
        audience_sport_id: 2,
        days_of_week: [1, 2],
        starts_at_time: '06:00',
        ends_at_time: '11:59',
        starts_on: '2026-09-01',
        ends_on: '2026-10-01',
        slots: [
            { slot_date: '2026-09-08' },
            { slot_date: '2026-09-04' },
        ],
        status: 'scheduled',
        discount_type: 'percentage',
        discount_value: '30',
    }, PROMOTION_STRATEGY.EXACT);

    assert.equal(normalized.promotion_type, 'specific_slots');
    assert.equal(normalized.goal, 'promote_specific_slots');
    assert.equal(normalized.resource_id, null);
    assert.equal(normalized.audience_sport_id, null);
    assert.deepEqual(normalized.days_of_week, []);
    assert.equal(normalized.starts_at_time, null);
    assert.equal(normalized.ends_at_time, null);
    assert.equal(normalized.starts_on, '2026-09-04');
    assert.equal(normalized.ends_on, '2026-09-08');
    assert.equal(normalized.is_public, true);
});

test('recurring and whole-scope strategies remove fields that do not apply', () => {
    const base = {
        goal: 'promote_specific_slots',
        resource_id: '',
        audience_sport_id: '',
        days_of_week: [1, 3],
        starts_at_time: '08:00',
        ends_at_time: '12:00',
        slots: [{ slot_date: '2026-09-04' }],
        status: 'draft',
        discount_type: '',
        discount_value: '30',
    };
    const recurring = normalizePromotionPayload(base, PROMOTION_STRATEGY.RECURRING);
    const scope = normalizePromotionPayload(base, PROMOTION_STRATEGY.SCOPE);

    assert.equal(recurring.promotion_type, 'time_window');
    assert.equal(recurring.goal, 'increase_off_peak_bookings');
    assert.deepEqual(recurring.slots, []);
    assert.deepEqual(recurring.days_of_week, [1, 3]);
    assert.equal(scope.promotion_type, 'venue');
    assert.equal(scope.goal, 'fill_empty_slots');
    assert.deepEqual(scope.slots, []);
    assert.deepEqual(scope.days_of_week, []);
    assert.equal(scope.starts_at_time, null);
    assert.equal(scope.discount_value, null);
    assert.equal(scope.is_public, false);
});

test('price preview mirrors booking and service-fee rounding', () => {
    const preview = promotionPricePreview({
        baseHourlyRate: '500.00',
        discountType: 'percentage',
        discountValue: '30.00',
        serviceFee: {
            type: 'percentage',
            percentage_basis_points: 500,
            fixed_amount: null,
            minimum_amount: '0.00',
            maximum_amount: null,
        },
    });

    assert.deepEqual(preview, {
        originalTotal: 500,
        venueTotal: 350,
        savings: 150,
        serviceFee: 17.5,
        playerTotal: 367.5,
    });
});

test('an incomplete special hourly price never previews a free booking', () => {
    const preview = promotionPricePreview({
        baseHourlyRate: '500.00',
        discountType: 'fixed_hourly_rate',
        discountValue: '',
        serviceFee: null,
    });

    assert.equal(preview.originalTotal, 500);
    assert.equal(preview.venueTotal, 500);
    assert.equal(preview.playerTotal, 500);
});

test('price preview respects fixed hourly offers and service-fee caps', () => {
    const preview = promotionPricePreview({
        baseHourlyRate: '650.00',
        durationMinutes: 90,
        discountType: 'fixed_hourly_rate',
        discountValue: '500.00',
        serviceFee: {
            type: 'percentage',
            percentage_basis_points: 500,
            fixed_amount: null,
            minimum_amount: '10.00',
            maximum_amount: '25.00',
        },
    });

    assert.deepEqual(preview, {
        originalTotal: 975,
        venueTotal: 750,
        savings: 225,
        serviceFee: 25,
        playerTotal: 775,
    });
});
