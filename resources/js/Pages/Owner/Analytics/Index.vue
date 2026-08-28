<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ report: Object, demand: Object, filters: Object, venues: Array, timezone: String });
const form = reactive({ ...props.filters });
const money = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 2 }).format(Number(value));
const number = (value) => new Intl.NumberFormat('en-PH').format(Number(value));
const trend = (value) => value === null ? 'Not enough prior data' : `${value > 0 ? '+' : ''}${value}% vs previous period`;
const metricCards = () => [
    ['Impressions', props.report.metrics.impressions, 'Published venue cards shown'],
    ['Venue views', props.report.metrics.profile_views, 'Deduplicated profile visits'],
    ['Availability views', props.report.metrics.availability_views, 'Schedules inspected'],
    ['Booking starts', props.report.metrics.booking_starts, 'Server-created starts'],
    ['Completed bookings', props.report.metrics.completed_bookings, 'Confirmed, non-refunded'],
    ['New customers', props.report.metrics.new_customers, 'First confirmed booking'],
];
const funnel = computed(() => [
    { label: 'Impressions', value: props.report.metrics.impressions },
    { label: 'Profile views', value: props.report.metrics.profile_views },
    { label: 'Availability views', value: props.report.metrics.availability_views },
    { label: 'Booking starts', value: props.report.metrics.booking_starts },
    { label: 'Completed', value: props.report.metrics.completed_bookings },
]);
const funnelMax = computed(() => Math.max(1, ...funnel.value.map((item) => Number(item.value))));
function applyFilters() { router.get('/owner/analytics', form, { preserveState: true, replace: true }); }
</script>

<template>
    <Head title="Marketplace analytics" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between"><div><p class="eyebrow">Marketplace evidence</p><h2 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Analytics & attribution</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Traffic is approximate and privacy-conscious. Booking and revenue totals use authoritative reservation and payment states.</p></div><form class="app-card grid gap-3 p-3 sm:grid-cols-4" @submit.prevent="applyFilters"><label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">From<input v-model="form.from" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" required></label><label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">To<input v-model="form.to" type="date" class="mt-1 block w-full rounded-lg border-slate-300 text-sm" required></label><label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Venue<AppSelect v-model="form.venue" :options="[{ value: '', label: 'All venues' }, ...venues.map((venue) => ({ value: venue.id, label: venue.name }))]" size="sm" class="mt-1 min-w-40 normal-case tracking-normal" aria-label="Venue filter" /></label><button class="self-end rounded-lg bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Apply</button></form></div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6"><section v-for="card in metricCards()" :key="card[0]" class="metric-card"><div class="metric-icon"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8" /><path d="M8 12h8M12 8v8" /></svg></div><p class="mt-4 text-sm text-slate-500">{{ card[0] }}</p><p class="mt-1 text-3xl font-semibold tracking-tight">{{ card[1] }}</p><p class="mt-2 text-xs leading-5 text-slate-400">{{ card[2] }}</p></section></div>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div><p class="eyebrow">Local player intent</p><h3 class="mt-1 text-xl font-semibold">Demand near your venues</h3><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Anonymous marketplace searches aggregated at city level. This is search interest, not guaranteed bookings or revenue.</p></div>
                        <div v-if="demand.eligible_areas.length" class="flex flex-wrap gap-2"><span v-for="area in demand.eligible_areas" :key="area.city_slug" class="rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800">{{ area.label }}</span></div>
                    </div>
                </div>

                <div v-if="!demand.available" class="px-6 py-12 text-center"><h4 class="font-semibold text-slate-900">Publish a venue to unlock local demand</h4><p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Demand is matched only to the city buckets of your published venues with active courts.</p></div>
                <div v-else-if="demand.privacy.suppressed" class="px-6 py-12 text-center"><h4 class="font-semibold text-slate-900">More local searches are needed</h4><p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">To protect player privacy, insights appear after at least {{ demand.privacy.minimum_unique_searchers }} distinct anonymous searching sessions exist in this market and date range.</p></div>
                <div v-else class="space-y-6 p-5 sm:p-6">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div v-for="metric in [
                            ['Searches near you', demand.metrics.searches, trend(demand.comparison.searches_percent)],
                            ['Estimated searchers', demand.metrics.unique_searchers, trend(demand.comparison.unique_searchers_percent)],
                            ['No available time', demand.metrics.no_availability, 'Venues matched, requested time did not'],
                            ['Unfulfilled demand', demand.metrics.unfulfilled_searches, trend(demand.comparison.unfulfilled_percent)],
                        ]" :key="metric[0]" class="rounded-2xl bg-slate-50 p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ metric[0] }}</p><p class="mt-3 text-3xl font-semibold text-slate-950">{{ number(metric[1]) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">{{ metric[2] }}</p></div>
                    </div>

                    <div class="grid gap-5 xl:grid-cols-3">
                        <div class="overflow-hidden rounded-2xl border border-slate-100"><div class="border-b border-slate-100 px-4 py-3"><h4 class="font-semibold">Demand by sport</h4></div><div class="divide-y divide-slate-100"><div v-for="sport in demand.sports" :key="sport.slug || 'any'" class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><div><p class="font-medium text-slate-800">{{ sport.label }}</p><p class="mt-1 text-xs text-slate-400">{{ number(sport.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(sport.searches) }}</strong></div><p v-if="!demand.sports.length" class="px-4 py-8 text-center text-sm text-slate-500">No sport group meets the privacy threshold.</p></div></div>
                        <div class="overflow-hidden rounded-2xl border border-slate-100"><div class="border-b border-slate-100 px-4 py-3"><h4 class="font-semibold">High-demand times</h4></div><div class="divide-y divide-slate-100"><div v-for="bucket in demand.time_buckets" :key="bucket.bucket" class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><div><p class="font-medium text-slate-800">{{ bucket.label }}</p><p class="mt-1 text-xs text-slate-400">{{ number(bucket.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(bucket.searches) }}</strong></div><p v-if="!demand.time_buckets.length" class="px-4 py-8 text-center text-sm text-slate-500">No time-specific group meets the privacy threshold.</p></div></div>
                        <div class="overflow-hidden rounded-2xl border border-slate-100"><div class="border-b border-slate-100 px-4 py-3"><h4 class="font-semibold">Requested days</h4></div><div class="divide-y divide-slate-100"><div v-for="day in demand.weekdays" :key="day.weekday" class="flex items-center justify-between gap-4 px-4 py-3 text-sm"><div><p class="font-medium text-slate-800">{{ day.label }}</p><p class="mt-1 text-xs text-slate-400">{{ number(day.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(day.searches) }}</strong></div><p v-if="!demand.weekdays.length" class="px-4 py-8 text-center text-sm text-slate-500">No date-specific group meets the privacy threshold.</p></div></div>
                    </div>

                    <p class="rounded-xl bg-court-50 px-4 py-3 text-xs leading-5 text-court-900">Privacy protection: no player name, contact detail, account ID, raw session identifier, or exact player location is included. Every displayed breakdown meets a minimum of {{ demand.privacy.minimum_unique_searchers }} distinct searching sessions.</p>
                </div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <section class="app-card p-5 sm:p-6"><div class="flex items-start justify-between gap-4"><div><p class="eyebrow">Conversion journey</p><h3 class="mt-1 text-xl font-semibold">Marketplace funnel</h3></div><span class="rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800">{{ report.metrics.conversion_rate }}% profile conversion</span></div><div class="mt-7 space-y-5"><div v-for="item in funnel" :key="item.label" class="grid grid-cols-[8rem_1fr_3rem] items-center gap-3"><p class="text-sm text-slate-500">{{ item.label }}</p><div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full min-w-1 rounded-full bg-[linear-gradient(90deg,#82ddb0,#146d4a)]" :style="{ width: `${Math.max(1.5, (Number(item.value) / funnelMax) * 100)}%` }"></div></div><p class="text-right text-sm font-semibold">{{ item.value }}</p></div></div></section>
                <section class="relative overflow-hidden rounded-2xl bg-court-950 p-6 text-white shadow-sm"><div class="absolute -right-16 -top-16 size-52 rounded-full border-[24px] border-white/5"></div><p class="relative text-xs font-semibold uppercase tracking-wider text-court-300">Attributed booking value</p><p class="relative mt-4 text-4xl font-semibold tracking-tight">{{ money(report.metrics.booking_revenue) }}</p><p class="relative mt-3 text-sm leading-6 text-court-100/65">Confirmed marketplace booking value in {{ timezone }}. Failed, cancelled, and refunded payments are excluded.</p><div class="relative mt-8 grid grid-cols-2 gap-3"><div class="rounded-xl bg-white/8 p-4"><p class="text-2xl font-semibold">{{ report.metrics.new_customers }}</p><p class="mt-1 text-xs text-court-100/60">New customers</p></div><div class="rounded-xl bg-white/8 p-4"><p class="text-2xl font-semibold">{{ report.metrics.returning_customers }}</p><p class="mt-1 text-xs text-court-100/60">Returning</p></div></div></section>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <section v-for="metric in [
                    ['Promoted bookings', report.acquisition_metrics.promoted_bookings, money(report.acquisition_metrics.promoted_revenue), 'Server-validated marketplace campaigns'],
                    ['Google bookings', report.acquisition_metrics.google_bookings, money(report.acquisition_metrics.google_revenue), 'Google Search and Maps combined'],
                    ['QR & referral bookings', report.acquisition_metrics.qr_referral_bookings, money(report.acquisition_metrics.qr_referral_revenue), 'Tagged QR codes and referral links'],
                ]" :key="metric[0]" class="app-card p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ metric[0] }}</p><div class="mt-3 flex items-end justify-between gap-3"><p class="text-3xl font-semibold">{{ metric[1] }}</p><p class="font-semibold text-court-700">{{ metric[2] }}</p></div><p class="mt-2 text-xs leading-5 text-slate-500">{{ metric[3] }}</p></section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-6 py-5"><p class="eyebrow">Attribution</p><h3 class="mt-1 text-xl font-semibold">Booking sources</h3><p class="mt-2 text-xs leading-5 text-slate-500">Last qualifying touch receives credit; a selected valid promotion overrides it. This is directional attribution, not proof of every influence.</p></div><div v-if="report.traffic_sources.length" class="divide-y divide-slate-100"><div v-for="source in report.traffic_sources" :key="source.source" class="grid grid-cols-[1fr_auto_auto] gap-4 px-6 py-4 text-sm"><div><span class="font-medium text-slate-800">{{ source.label }}</span><p class="mt-1 text-xs text-slate-400">{{ source.new_customers }} new customers</p></div><span class="self-center text-right text-slate-500">{{ source.bookings }} bookings</span><span class="self-center text-right font-semibold">{{ money(source.revenue) }}</span></div></div><p v-else class="px-6 py-10 text-center text-sm text-slate-500">No attributed completed bookings in this range.</p></section>
                <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-6 py-5"><p class="eyebrow">Campaigns</p><h3 class="mt-1 text-xl font-semibold">Promotion performance</h3></div><div v-if="report.promotions.length" class="divide-y divide-slate-100"><div v-for="promotion in report.promotions" :key="promotion.id" class="px-6 py-4"><div class="flex items-start justify-between gap-3"><div><p class="font-medium">{{ promotion.title }}</p><p class="mt-1 text-xs text-slate-400">{{ promotion.venue }} · {{ promotion.campaign_token }}</p></div><p class="font-semibold">{{ money(promotion.revenue) }}</p></div><div class="mt-3 flex flex-wrap gap-2 text-xs"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ promotion.impressions }} impressions</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ promotion.clicks }} clicks</span><span class="rounded-full bg-court-50 px-2.5 py-1 font-semibold text-court-800">{{ promotion.bookings }} bookings</span></div></div></div><p v-else class="px-6 py-10 text-center text-sm text-slate-500">No promotions match this view.</p></section>
            </div>
        </div>
    </OwnerLayout>
</template>
