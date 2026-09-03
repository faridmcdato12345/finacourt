export const PROMOTION_STRATEGY = Object.freeze({
    EXACT: 'exact',
    RECURRING: 'recurring',
    SCOPE: 'scope',
});

export function strategyForPromotionType(type) {
    if (type === 'specific_slots') return PROMOTION_STRATEGY.EXACT;
    if (type === 'time_window') return PROMOTION_STRATEGY.RECURRING;

    return PROMOTION_STRATEGY.SCOPE;
}

export function normalizePromotionPayload(data, strategy) {
    const normalized = { ...data };

    if (strategy === PROMOTION_STRATEGY.EXACT) {
        const slotDates = normalized.slots
            .map((slot) => slot.slot_date)
            .filter(Boolean)
            .sort();

        normalized.promotion_type = 'specific_slots';
        normalized.goal = ['promote_specific_slots', 'promote_today_or_tonight'].includes(normalized.goal)
            ? normalized.goal
            : 'promote_specific_slots';
        normalized.resource_id = null;
        normalized.audience_sport_id = null;
        normalized.days_of_week = [];
        normalized.starts_at_time = null;
        normalized.ends_at_time = null;

        if (slotDates.length > 0) {
            normalized.starts_on = slotDates[0];
            normalized.ends_on = slotDates[slotDates.length - 1];
        }
    } else if (strategy === PROMOTION_STRATEGY.RECURRING) {
        normalized.promotion_type = 'time_window';
        normalized.goal = 'increase_off_peak_bookings';
        if (normalized.resource_id) normalized.audience_sport_id = null;
        normalized.slots = [];
    } else {
        normalized.promotion_type = normalized.resource_id ? 'resource' : 'venue';
        normalized.goal = normalized.goal === 'get_new_customers' ? normalized.goal : 'fill_empty_slots';
        if (normalized.resource_id) normalized.audience_sport_id = null;
        normalized.days_of_week = [];
        normalized.starts_at_time = null;
        normalized.ends_at_time = null;
        normalized.slots = [];
    }

    normalized.is_public = ['scheduled', 'active'].includes(normalized.status);

    if (!normalized.discount_type) normalized.discount_value = null;

    return normalized;
}

export function promotionPricePreview({
    baseHourlyRate,
    durationMinutes = 60,
    discountType = null,
    discountValue = null,
    serviceFee = null,
}) {
    const originalUnitCents = moneyToCents(baseHourlyRate);
    const discountCents = moneyToCents(discountValue);
    const hasDiscount = Number(discountValue) > 0;
    let unitCents = originalUnitCents;

    if (discountType === 'percentage' && hasDiscount) {
        const percentageBasisPoints = Math.min(10000, discountCents);
        unitCents = Math.max(0, Math.floor(
            (originalUnitCents * (10000 - percentageBasisPoints) + 5000) / 10000,
        ));
    } else if (discountType === 'fixed_hourly_rate' && hasDiscount) {
        unitCents = Math.min(originalUnitCents, discountCents);
    }

    const originalTotalCents = durationTotal(originalUnitCents, durationMinutes);
    const venueTotalCents = durationTotal(unitCents, durationMinutes);
    const serviceFeeCents = calculateServiceFee(venueTotalCents, serviceFee);

    return {
        originalTotal: centsToNumber(originalTotalCents),
        venueTotal: centsToNumber(venueTotalCents),
        savings: centsToNumber(originalTotalCents - venueTotalCents),
        serviceFee: centsToNumber(serviceFeeCents),
        playerTotal: centsToNumber(venueTotalCents + serviceFeeCents),
    };
}

function calculateServiceFee(venueCents, policy) {
    if (!policy) return 0;

    let feeCents = policy.type === 'percentage'
        ? Math.floor((venueCents * Number(policy.percentage_basis_points || 0) + 5000) / 10000)
        : moneyToCents(policy.fixed_amount);
    const minimum = moneyToCents(policy.minimum_amount);
    const maximum = policy.maximum_amount === null || policy.maximum_amount === undefined
        ? null
        : moneyToCents(policy.maximum_amount);

    feeCents = Math.max(feeCents, minimum, 0);

    return maximum === null ? feeCents : Math.min(feeCents, maximum);
}

function durationTotal(unitCents, durationMinutes) {
    return Math.floor((unitCents * Number(durationMinutes) + 30) / 60);
}

function moneyToCents(value) {
    const amount = Number(value || 0);

    return Number.isFinite(amount) ? Math.round(amount * 100) : 0;
}

function centsToNumber(cents) {
    return cents / 100;
}
