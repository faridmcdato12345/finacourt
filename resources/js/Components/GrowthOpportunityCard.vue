<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { primaryOpportunitySignal } from '../lib/growth-opportunities.js';

const props = defineProps({
    recommendation: { type: Object, required: true },
    snoozeOptions: { type: Array, default: () => [] },
    featured: { type: Boolean, default: false },
});

const emit = defineEmits(['change-state']);

const evidenceLabels = {
    empty_slot_count: 'Open court times',
    last_minute_slot_count: 'Within 24 hours',
    horizon_days: 'Days checked',
    searches: 'Player searches',
    unique_searchers: 'Different searchers',
    available_slot_count: 'Open court times',
    lookback_days: 'Days reviewed',
    city: 'City',
    sport: 'Sport',
    date: 'Date',
    start: 'Start time',
    end: 'End time',
    segment: 'Player group',
    unfulfilled_searches: 'Searches without a good match',
    no_results: 'No matching venue',
    no_availability: 'No matching time',
    inactive_customer_count: 'Past players',
    inactive_days: 'Days since last booking',
    minimum_group_size: 'Minimum group size',
    qualified_bookings: 'Confirmed bookings',
    qualified_booking_value: 'Confirmed booking value',
    profile_views: 'Venue page visits',
    unique_visitors: 'Different visitors',
    conversion_rate_percent: 'Visits that became bookings',
    stronger_source: 'Better source',
    stronger_unique_visitors: 'Visitors from better source',
    stronger_qualified_bookings: 'Bookings from better source',
    stronger_conversion_rate_percent: 'Booking rate from better source',
    comparison_source: 'Compared with',
    comparison_unique_visitors: 'Visitors from compared source',
    comparison_qualified_bookings: 'Bookings from compared source',
    comparison_conversion_rate_percent: 'Booking rate from compared source',
    gap_percentage_points: 'Difference',
};
const hiddenEvidence = new Set(['promotion_id', 'currency', 'campaign_status', 'resource', 'venue', 'scan_capped']);
const sourceLabels = {
    marketplace_organic: 'FinACourt search',
    marketplace_promotion: 'FinACourt promotion',
    google_organic: 'Google Search',
    google_maps: 'Google Maps',
    facebook: 'Facebook',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    qr_code: 'QR code',
    referral: 'Referral link',
    sales_partner: 'Partner referral',
    direct: 'Direct visit',
    unknown: 'Unknown',
};

const signal = computed(() => primaryOpportunitySignal(props.recommendation));
const evidence = computed(() => Object.entries(props.recommendation.evidence || {}).filter(([key]) => !hiddenEvidence.has(key)));
const priorityLabel = computed(() => ({
    high: 'Important',
    medium: 'Worth trying',
    low: 'When you have time',
}[props.recommendation.priority] || 'Opportunity'));

const label = (key) => evidenceLabels[key] || String(key).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const number = (value) => new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(Number(value));
const display = (value, key) => {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (['stronger_source', 'comparison_source'].includes(key)) return sourceLabels[value] || value;
    if (key === 'segment' && value === 'inactive_30') return 'No booking in 30 days';
    if (key.includes('rate_percent')) return `${number(value)}%`;
    if (key === 'gap_percentage_points') return `${number(value)} points`;
    if (key === 'qualified_booking_value') {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: props.recommendation.evidence?.currency || 'PHP',
            maximumFractionDigits: 0,
        }).format(Number(value));
    }

    return typeof value === 'number' ? number(value) : value;
};
</script>

<template>
    <article :class="['app-card relative', featured ? 'p-5 sm:p-7' : 'flex h-full flex-col p-5 sm:p-6']">
        <div class="absolute right-4 top-4 z-20 sm:right-5 sm:top-5">
            <details class="group relative">
                <summary class="grid size-10 cursor-pointer list-none place-items-center rounded-xl border border-slate-200 bg-white text-lg font-bold tracking-widest text-slate-500 shadow-sm hover:border-court-300 hover:text-court-800 [&::-webkit-details-marker]:hidden" aria-label="More actions for this opportunity">•••</summary>
                <div class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Remind me later</p>
                    <button v-for="option in snoozeOptions" :key="option.value" type="button" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="emit('change-state', recommendation, 'snoozed', option.value)">In {{ option.label }}</button>
                    <div class="my-1 border-t border-slate-100"></div>
                    <button type="button" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50" @click="emit('change-state', recommendation, 'dismissed')">Dismiss opportunity</button>
                </div>
            </details>
        </div>

        <div :class="featured ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center' : 'pb-5'">
            <div class="min-w-0 pr-12">
                <p v-if="featured" class="eyebrow">Recommended first</p>
                <div :class="['flex flex-wrap items-center gap-2', featured ? 'mt-3' : '']">
                    <span :class="recommendation.priority === 'high' ? 'bg-amber-50 text-amber-800' : recommendation.priority === 'medium' ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider">{{ priorityLabel }}</span>
                    <span class="text-xs font-medium text-slate-500">{{ recommendation.type_label }}</span>
                    <span v-if="recommendation.venue" class="text-xs text-slate-400">· {{ recommendation.venue }}</span>
                </div>
                <h2 :class="['font-semibold tracking-tight text-slate-950', featured ? 'mt-4 text-2xl sm:text-3xl' : 'mt-3 text-lg']">{{ recommendation.title }}</h2>
                <p :class="['text-sm leading-6 text-slate-600', featured ? 'mt-3 max-w-3xl' : 'mt-2']">{{ recommendation.explanation }}</p>
            </div>

            <div :class="['mt-5 rounded-2xl p-5 lg:mt-0', featured ? 'bg-court-950 text-white' : 'bg-court-50 text-court-950']">
                <p :class="['text-4xl font-semibold tracking-tight', featured ? 'text-white' : 'text-court-900']">{{ signal.value }}</p>
                <p :class="['mt-2 text-sm leading-5', featured ? 'text-court-100' : 'text-court-800']">{{ signal.label }}</p>
            </div>
        </div>

        <div :class="['flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center', featured ? 'mt-5' : 'mt-auto']">
            <Link :href="recommendation.suggested_action.url" class="rounded-xl bg-court-700 px-5 py-3 text-center text-sm font-semibold text-white hover:bg-court-800">{{ recommendation.suggested_action.label }} →</Link>
            <details class="group min-w-0 sm:ml-auto sm:flex-1">
                <summary class="cursor-pointer list-none rounded-lg px-2 py-2 text-center text-sm font-semibold text-court-700 hover:bg-court-50 sm:text-right [&::-webkit-details-marker]:hidden">Why this is recommended <span class="inline-block transition group-open:rotate-180">⌄</span></summary>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div v-for="([key, value]) in evidence" :key="key" class="rounded-xl bg-slate-50 px-3 py-2.5">
                        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ label(key) }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800">{{ display(value, key) }}</dd>
                    </div>
                </dl>
            </details>
        </div>
    </article>
</template>
