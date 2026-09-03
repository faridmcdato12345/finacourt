export const OPPORTUNITY_GROUPS = [
    {
        key: 'fill-open-times',
        title: 'Fill open court times',
        description: 'Use live availability and nearby player demand before those court times pass.',
        types: ['empty_inventory', 'demand_with_inventory', 'unfulfilled_demand'],
    },
    {
        key: 'bring-players-back',
        title: 'Bring past players back',
        description: 'Invite eligible previous customers to book again without exposing individual activity.',
        types: ['inactive_customers'],
    },
    {
        key: 'improve-visibility',
        title: 'Improve venue visibility',
        description: 'Turn more venue-page visits and acquisition channels into confirmed bookings.',
        types: ['low_booking_conversion', 'channel_conversion_gap'],
    },
    {
        key: 'repeat-promotions',
        title: 'Repeat successful promotions',
        description: 'Review promotions that already generated qualified bookings before running them again.',
        types: ['repeat_successful_campaign'],
    },
];

const number = (value) => new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(Number(value || 0));

export function primaryOpportunitySignal(recommendation = {}) {
    const evidence = recommendation.evidence || {};

    switch (recommendation.type) {
        case 'empty_inventory': {
            const urgentCount = Number(evidence.last_minute_slot_count || 0);

            return urgentCount > 0
                ? { value: number(urgentCount), label: 'open court times in the next 24 hours' }
                : { value: number(evidence.empty_slot_count), label: `open court times in the next ${number(evidence.horizon_days)} days` };
        }
        case 'demand_with_inventory':
            return { value: number(evidence.searches), label: `player searches in the last ${number(evidence.lookback_days)} days` };
        case 'unfulfilled_demand':
            return { value: number(evidence.unfulfilled_searches), label: 'nearby searches without a good match' };
        case 'inactive_customers':
            return { value: number(evidence.inactive_customer_count), label: 'past players eligible to invite back' };
        case 'repeat_successful_campaign':
            return { value: number(evidence.qualified_bookings), label: 'confirmed bookings from this promotion' };
        case 'low_booking_conversion':
            return { value: `${number(evidence.conversion_rate_percent)}%`, label: 'of venue-page visits became bookings' };
        case 'channel_conversion_gap':
            return { value: `${number(evidence.gap_percentage_points)} pts`, label: 'difference in booking conversion rate' };
        default:
            return { value: 'Ready', label: 'recommended action available' };
    }
}

export function groupOpportunities(recommendations = []) {
    const grouped = OPPORTUNITY_GROUPS
        .map((group) => ({
            ...group,
            recommendations: recommendations.filter((recommendation) => group.types.includes(recommendation.type)),
        }))
        .filter((group) => group.recommendations.length > 0);
    const knownTypes = new Set(OPPORTUNITY_GROUPS.flatMap((group) => group.types));
    const uncategorized = recommendations.filter((recommendation) => !knownTypes.has(recommendation.type));

    if (uncategorized.length > 0) {
        grouped.push({
            key: 'other-opportunities',
            title: 'Other growth opportunities',
            description: 'Additional actions FinACourt identified from your current venue activity.',
            types: [],
            recommendations: uncategorized,
        });
    }

    return grouped;
}

export function relativeUpdatedAt(value, now = Date.now()) {
    const updatedAt = new Date(value).getTime();

    if (!Number.isFinite(updatedAt)) {
        return 'recently';
    }

    const seconds = Math.max(0, Math.floor((now - updatedAt) / 1000));

    if (seconds < 60) return 'just now';

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;

    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} ${days === 1 ? 'day' : 'days'} ago`;

    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium' }).format(new Date(updatedAt));
}
