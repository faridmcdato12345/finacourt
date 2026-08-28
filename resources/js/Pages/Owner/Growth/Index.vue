<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ report: Object, snoozeOptions: Array });
const snoozeDays = reactive({});
const number = (value) => new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(Number(value));
const evidenceLabels = {
    empty_slot_count: 'Open court times',
    last_minute_slot_count: 'Within 24 hours',
    horizon_days: 'Days checked',
    scan_capped: 'More times may exist',
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
    promotion_id: 'Deal ID',
    qualified_bookings: 'Confirmed bookings',
    qualified_booking_value: 'Booked court value',
    currency: 'Currency',
    campaign_status: 'Deal status',
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
const label = (key) => evidenceLabels[key] || String(key).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const priorityLabel = (priority) => ({ high: 'Important', medium: 'Worth trying', low: 'When you have time' }[priority] || label(priority));
const hiddenEvidence = new Set(['promotion_id', 'currency', 'campaign_status', 'resource', 'venue']);
const sourceLabels = {
    marketplace_organic: 'FinACourt search',
    marketplace_promotion: 'FinACourt deal',
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
const visibleEvidence = (evidence = {}) => Object.fromEntries(Object.entries(evidence).filter(([key]) => !hiddenEvidence.has(key)));
const display = (value, key = null) => {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (key && ['stronger_source', 'comparison_source'].includes(key)) return sourceLabels[value] || numberOrText(value);
    if (key === 'segment' && value === 'inactive_30') return 'No booking in 30 days';
    if (key && key.includes('rate_percent')) return `${number(value)}%`;
    if (key === 'gap_percentage_points') return `${number(value)} points`;

    return numberOrText(value);
};
const numberOrText = (value) => typeof value === 'number' ? number(value) : value;

function setState(recommendation, status) {
    router.post(`/owner/growth/${recommendation.key}/state`, {
        status,
        snooze_days: status === 'snoozed' ? (snoozeDays[recommendation.key] || props.snoozeOptions[0]?.value) : null,
    }, { preserveScroll: true });
}

function restore(recommendation) {
    router.delete(`/owner/growth/${recommendation.key}/state`, { preserveScroll: true });
}
</script>

<template>
    <Head title="More bookings" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Helpful next steps</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Ways to get more bookings</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Simple suggestions based on real player searches, bookings, deals, and past players. Nothing happens unless you choose it.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                    Last checked <strong class="text-slate-800">{{ new Date(report.calculated_at).toLocaleString() }}</strong>
                </div>
            </div>

            <section v-if="report.active.length" class="grid gap-5 xl:grid-cols-2">
                <article v-for="recommendation in report.active" :key="recommendation.key" class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 p-5 sm:p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="recommendation.priority === 'high' ? 'bg-amber-50 text-amber-800' : recommendation.priority === 'medium' ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider">{{ priorityLabel(recommendation.priority) }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500">{{ recommendation.type_label }}</span>
                            <span v-if="recommendation.venue" class="text-xs text-slate-400">{{ recommendation.venue }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-semibold tracking-tight text-slate-950">{{ recommendation.title }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ recommendation.explanation }}</p>
                    </div>
                    <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-end">
                        <dl class="grid gap-2 sm:grid-cols-2">
                            <div v-for="(value, key) in visibleEvidence(recommendation.evidence)" :key="key" class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ label(key) }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ display(value, key) }}</dd>
                            </div>
                        </dl>
                        <Link :href="recommendation.suggested_action.url" class="rounded-xl bg-court-700 px-4 py-3 text-center text-sm font-semibold text-white">{{ recommendation.suggested_action.label }} →</Link>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6">
                        <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'dismissed')">Hide</button>
                        <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'resolved')">Done</button>
                        <div class="ml-auto flex items-center gap-2">
                            <AppSelect v-model="snoozeDays[recommendation.key]" :options="snoozeOptions" size="sm" class="min-w-28 bg-white" />
                            <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'snoozed')">Remind me later</button>
                        </div>
                    </div>
                </article>
            </section>

            <section v-else class="app-card px-6 py-16 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-court-50 text-xl text-court-700">✓</div>
                <h2 class="mt-4 text-xl font-semibold text-slate-900">No suggestions yet</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">FinACourt waits until there is enough real activity before suggesting something. It does not invent numbers.</p>
                <div class="mt-5 flex justify-center gap-3"><Link href="/owner/analytics" class="text-sm font-semibold text-court-700">View visits and bookings</Link><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">View deals</Link></div>
            </section>

            <section v-if="report.suppressed.length" class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Your choices</p><h2 class="mt-1 text-xl font-semibold">Hidden suggestions</h2><p class="mt-2 text-sm text-slate-500">Hidden and done suggestions stay out of the way until you bring them back. Reminders return automatically later.</p></div>
                <div class="divide-y divide-slate-100">
                    <div v-for="recommendation in report.suppressed" :key="recommendation.key" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div><p class="font-semibold text-slate-900">{{ recommendation.title }}</p><p class="mt-1 text-xs text-slate-400">{{ recommendation.state_label }}<span v-if="recommendation.snoozed_until"> until {{ new Date(recommendation.snoozed_until).toLocaleString() }}</span></p></div>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-court-700" @click="restore(recommendation)">Show again</button>
                    </div>
                </div>
            </section>

            <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-xs leading-5 text-slate-500">Suggestions refresh every day. To protect players, search breakdowns only appear when enough different people searched. Booked values do not include cancelled bookings, failed payments, or refunded payments.</p>
        </div>
    </OwnerLayout>
</template>
