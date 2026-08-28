<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({ report: Object, acquisition: Object, filters: Object, timezone: String });
const form = reactive({ ...props.filters });
const demand = computed(() => props.acquisition.demand);
const money = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value));
const number = (value) => new Intl.NumberFormat('en-PH').format(Number(value));
const funnelMaximum = computed(() => Math.max(...props.acquisition.funnel.map((step) => Number(step.value)), 1));
const funnelWidth = (value) => `${Math.max(Number(value) === 0 ? 0 : 5, (Number(value) / funnelMaximum.value) * 100)}%`;
const sourceLabel = (value) => String(value || 'direct').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const pitchPoints = computed(() => [
    `${number(props.acquisition.metrics.searches)} marketplace searches recorded in the selected period.`,
    `${number(props.acquisition.metrics.high_intent_searches)} searches included a requested date or start time.`,
    `${number(props.acquisition.metrics.zero_result_searches)} searches returned no matching inventory.`,
    `${number(props.acquisition.metrics.no_availability_searches)} searches matched venues but found no bookable time.`,
    `${number(props.report.metrics.availability_views)} availability checks and ${number(props.report.metrics.booking_starts)} booking starts show intent beyond page traffic.`,
]);

function applyFilters() {
    router.get('/platform/analytics', form, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Owner acquisition intelligence" />
    <PlatformLayout>
        <div class="space-y-7">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="eyebrow">Platform growth</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Owner acquisition intelligence</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Turn first-party marketplace demand into truthful, privacy-conscious evidence for court owners who have not joined yet.</p>
                </div>
                <form class="app-card flex flex-wrap items-end gap-3 p-3" @submit.prevent="applyFilters">
                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">From<input v-model="form.from" type="date" class="mt-1 block rounded-lg border-slate-300 text-sm" required></label>
                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">To<input v-model="form.to" type="date" class="mt-1 block rounded-lg border-slate-300 text-sm" required></label>
                    <button class="rounded-lg bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Apply</button>
                </form>
            </div>

            <section class="relative overflow-hidden rounded-3xl bg-court-950 p-6 text-white shadow-xl shadow-court-950/10 sm:p-8">
                <div class="court-visual absolute inset-y-0 right-0 hidden w-2/5 opacity-40 lg:block"></div>
                <div class="relative grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Prospect-ready proof</p>
                        <h2 class="mt-3 max-w-xl text-3xl font-semibold tracking-tight sm:text-4xl">Show demand owners can act on—not speculative lost revenue.</h2>
                        <p class="mt-4 max-w-xl text-sm leading-6 text-court-100/70">Use these platform facts in outreach. Counts reflect the selected period; confirmed booking value remains server-authoritative.</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="point in pitchPoints" :key="point" class="rounded-2xl border border-white/10 bg-white/8 p-4 text-sm leading-6 text-court-50 backdrop-blur"><span class="mr-2 text-court-300">✓</span>{{ point }}</div>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section v-for="metric in [
                    ['Player searches', number(acquisition.metrics.searches), 'All marketplace discovery requests'],
                    ['Estimated searchers', number(acquisition.metrics.unique_searchers), 'Anonymous session-based estimate'],
                    ['Date/time intent', number(acquisition.metrics.high_intent_searches), 'Searches specifying when to play'],
                    ['No inventory', number(acquisition.metrics.zero_result_searches), 'No matching published venue'],
                    ['No availability', number(acquisition.metrics.no_availability_searches), `${acquisition.metrics.search_coverage_rate}% search coverage`],
                ]" :key="metric[0]" class="metric-card">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ metric[0] }}</p>
                    <p class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">{{ metric[1] }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ metric[2] }}</p>
                </section>
            </div>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Demand mix</p><h2 class="mt-1 text-xl font-semibold">What, where, and when players searched</h2><p class="mt-2 text-sm text-slate-500">Normalized aggregate dimensions; demo events and raw visitor history are excluded.</p></div>
                <div class="grid gap-px bg-slate-100 lg:grid-cols-3">
                    <div class="bg-white p-5 sm:p-6"><h3 class="font-semibold">Sports</h3><div class="mt-4 space-y-3"><div v-for="sport in demand.sports" :key="sport.slug || 'any'" class="flex items-center justify-between gap-4 text-sm"><div><p class="font-medium text-slate-800">{{ sport.label }}</p><p class="text-xs text-slate-400">{{ number(sport.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(sport.searches) }}</strong></div><p v-if="!demand.sports.length" class="text-sm text-slate-500">No sport demand recorded.</p></div></div>
                    <div class="bg-white p-5 sm:p-6"><h3 class="font-semibold">Areas</h3><div class="mt-4 space-y-3"><div v-for="area in demand.areas" :key="area.city_slug || 'any'" class="flex items-center justify-between gap-4 text-sm"><div><p class="font-medium text-slate-800">{{ area.label }}</p><p class="text-xs text-slate-400">{{ number(area.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(area.searches) }}</strong></div><p v-if="!demand.areas.length" class="text-sm text-slate-500">No area demand recorded.</p></div></div>
                    <div class="bg-white p-5 sm:p-6"><h3 class="font-semibold">Time periods</h3><div class="mt-4 space-y-3"><div v-for="bucket in demand.time_buckets" :key="bucket.bucket" class="flex items-center justify-between gap-4 text-sm"><div><p class="font-medium text-slate-800">{{ bucket.label }}</p><p class="text-xs text-slate-400">{{ number(bucket.unfulfilled_searches) }} unfulfilled</p></div><strong>{{ number(bucket.searches) }}</strong></div><p v-if="!demand.time_buckets.length" class="text-sm text-slate-500">No time-specific demand recorded.</p></div></div>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Demand journey</p><div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><h2 class="text-xl font-semibold">Marketplace acquisition funnel</h2><p class="text-xs text-slate-400">Stages are signals, not necessarily the same individual cohort.</p></div></div>
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                    <div class="space-y-4">
                        <div v-for="step in acquisition.funnel" :key="step.label" class="grid grid-cols-[8.5rem_1fr_3.5rem] items-center gap-3 text-sm"><span class="text-slate-600">{{ step.label }}</span><div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-court-600" :style="{ width: funnelWidth(step.value) }"></div></div><strong class="text-right text-slate-900">{{ number(step.value) }}</strong></div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-court-50 p-5"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Availability intent</p><p class="mt-3 text-3xl font-semibold text-court-950">{{ number(report.metrics.availability_views) }}</p><p class="mt-2 text-xs leading-5 text-court-800/70">Visitors progressed from venue content to checking a real schedule.</p></div>
                        <div class="rounded-2xl bg-slate-950 p-5 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-court-300">Confirmed value</p><p class="mt-3 text-3xl font-semibold">{{ money(report.metrics.booking_revenue) }}</p><p class="mt-2 text-xs leading-5 text-slate-300">Excludes failed, cancelled, and refunded payment states.</p></div>
                    </div>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Supply gaps</p><h2 class="mt-1 text-xl font-semibold">Where court owners could capture demand</h2><p class="mt-2 text-sm text-slate-500">Prioritized by searches, time-specific intent, and searches that returned no inventory.</p></div>
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3 sm:px-6">Market</th><th class="px-5 py-3">Searches</th><th class="px-5 py-3">Searchers</th><th class="px-5 py-3">Date/time intent</th><th class="px-5 py-3">No inventory</th><th class="px-5 py-3">No availability</th><th class="px-5 py-3">Coverage</th><th class="px-5 py-3 sm:px-6">Owner outreach angle</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="segment in acquisition.demand_segments" :key="`${segment.city}-${segment.sport}`"><td class="px-5 py-4 sm:px-6"><p class="font-semibold text-slate-900">{{ segment.sport }}</p><p class="mt-1 text-xs text-slate-400">{{ segment.city }}</p></td><td class="px-5 py-4 font-medium">{{ number(segment.searches) }}</td><td class="px-5 py-4">{{ number(segment.unique_searchers) }}</td><td class="px-5 py-4">{{ number(segment.high_intent_searches) }}</td><td class="px-5 py-4"><span :class="segment.zero_results ? 'bg-amber-50 text-amber-800' : 'bg-court-50 text-court-800'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ number(segment.zero_results) }}</span></td><td class="px-5 py-4"><span :class="segment.no_availability ? 'bg-amber-50 text-amber-800' : 'bg-court-50 text-court-800'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ number(segment.no_availability) }}</span></td><td class="px-5 py-4">{{ segment.coverage_rate }}%</td><td class="max-w-xs px-5 py-4 text-xs leading-5 text-slate-500 sm:px-6"><span v-if="segment.no_availability">Matching venues could not serve the requested time.</span><span v-else-if="segment.zero_results">Players searched here without finding a matching court.</span><span v-else-if="segment.high_intent_searches">Players specified when they wanted to play.</span><span v-else>Measured discovery demand in this market.</span></td></tr>
                        <tr v-if="!acquisition.demand_segments.length"><td colspan="8" class="px-6 py-10 text-center text-slate-500">No search demand has been recorded in this period yet.</td></tr>
                    </tbody>
                </table></div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <p class="eyebrow">Venue traffic</p>
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold">Visitors by venue</h2>
                            <p class="mt-2 text-sm text-slate-500">Includes bookable venues and guide-only venues that are not bookable yet, so you can show owners real interest before they join.</p>
                        </div>
                        <p class="text-xs leading-5 text-slate-400">Visitors are anonymous session-based estimates.</p>
                    </div>
                </div>
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 sm:px-6">Venue</th>
                            <th class="px-5 py-3">Organization</th>
                            <th class="px-5 py-3 text-right">Estimated visitors</th>
                            <th class="px-5 py-3 text-right">Profile visits</th>
                            <th class="px-5 py-3 text-right">Impressions</th>
                            <th class="px-5 py-3 text-right">Availability checks</th>
                            <th class="px-5 py-3 text-right">Bookings</th>
                            <th class="px-5 py-3 text-right sm:px-6">Confirmed value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="venue in report.venues" :key="venue.key || venue.id">
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900">{{ venue.name }}</p>
                                    <span :class="venue.booking_status === 'Bookable on FinACourt' ? 'bg-court-50 text-court-800' : 'bg-amber-50 text-amber-800'" class="rounded-full px-2.5 py-1 text-[11px] font-semibold">{{ venue.booking_status }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ venue.location || 'Location not set' }}</p>
                                <p v-if="venue.sports?.length" class="mt-1 text-xs text-slate-400">{{ venue.sports.join(', ') }}</p>
                                <a v-if="venue.public_url" :href="venue.public_url" class="mt-2 inline-flex text-xs font-semibold text-court-700">Open public page ↗</a>
                            </td>
                            <td class="px-5 py-4 text-slate-600"><p>{{ venue.organization || 'No organization' }}</p><p class="mt-1 text-xs text-slate-400">{{ venue.status_label }}</p></td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-950">{{ number(venue.unique_visitors) }}</td>
                            <td class="px-5 py-4 text-right">{{ number(venue.profile_views) }}</td>
                            <td class="px-5 py-4 text-right">{{ number(venue.impressions) }}</td>
                            <td class="px-5 py-4 text-right">{{ number(venue.availability_views) }}</td>
                            <td class="px-5 py-4 text-right">{{ number(venue.bookings) }}</td>
                            <td class="px-5 py-4 text-right font-semibold sm:px-6">{{ money(venue.revenue) }}</td>
                        </tr>
                        <tr v-if="!report.venues.length"><td colspan="8" class="px-6 py-10 text-center text-slate-500">No venue traffic has been recorded in this period yet.</td></tr>
                    </tbody>
                </table></div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Claim pipeline</p><h2 class="mt-1 text-xl font-semibold">Unclaimed venue evidence</h2><p class="mt-2 text-sm text-slate-500">Use only attributable listing activity. No visitor identity is exposed.</p></div>
                    <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3 sm:px-6">Venue</th><th class="px-5 py-3 text-right">Visitors</th><th class="px-5 py-3 text-right">Profile views</th><th class="px-5 py-3 text-right">Availability</th><th class="px-5 py-3 sm:px-6">Evidence</th></tr></thead><tbody class="divide-y divide-slate-100">
                        <tr v-for="venue in acquisition.prospect_venues" :key="venue.id"><td class="px-5 py-4 sm:px-6"><p class="font-semibold text-slate-900">{{ venue.name }}</p><p class="mt-1 text-xs text-slate-400">{{ venue.location }} · {{ venue.sports.join(', ') || 'Sport not set' }}</p></td><td class="px-5 py-4 text-right">{{ number(venue.unique_visitors) }}</td><td class="px-5 py-4 text-right">{{ number(venue.profile_views) }}</td><td class="px-5 py-4 text-right">{{ number(venue.availability_views) }}</td><td class="px-5 py-4 sm:px-6"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ venue.listing_state }}</span><a v-if="venue.listing_state === 'Public listing'" :href="`/venues/${venue.slug}`" class="ml-3 text-xs font-semibold text-court-700">Open ↗</a></td></tr>
                        <tr v-if="!acquisition.prospect_venues.length"><td colspan="5" class="px-6 py-10 text-center text-slate-500">No unclaimed venue records are available. Search-demand gaps can still guide owner outreach.</td></tr>
                    </tbody></table></div>
                </section>

                <section class="app-card p-5 sm:p-6"><p class="eyebrow">Current supply</p><h2 class="mt-1 text-xl font-semibold">Marketplace adoption</h2><div class="mt-6 grid grid-cols-2 gap-3"><div v-for="item in [
                    ['Owner organizations', acquisition.supply.registered_owner_organizations], ['Claimed venues', acquisition.supply.claimed_venues], ['Unclaimed venues', acquisition.supply.unclaimed_venues], ['Published venues', acquisition.supply.published_venues], ['Active courts', acquisition.supply.active_courts], ['Active cities', acquisition.supply.active_cities],
                ]" :key="item[0]" class="rounded-xl bg-slate-50 p-4"><p class="text-2xl font-semibold text-slate-950">{{ number(item[1]) }}</p><p class="mt-1 text-xs text-slate-500">{{ item[0] }}</p></div></div><p class="mt-5 rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Supply counts are current inventory, while traffic and funnel metrics follow the selected date range.</p></section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Acquisition source</p><h2 class="mt-1 text-xl font-semibold">Confirmed bookings by source</h2><p class="mt-2 text-xs leading-5 text-slate-500">Immutable booking snapshots using the documented last-touch rule with a verified-promotion override.</p></div><div class="divide-y divide-slate-100"><div v-for="source in report.traffic_sources" :key="source.source" class="flex items-center justify-between gap-5 px-5 py-4 sm:px-6"><div><p class="font-medium text-slate-900">{{ source.label || sourceLabel(source.source) }}</p><p class="mt-1 text-xs text-slate-400">{{ number(source.bookings) }} confirmed bookings · {{ number(source.new_customers) }} new customers</p></div><p class="font-semibold">{{ money(source.revenue) }}</p></div><p v-if="!report.traffic_sources.length" class="px-6 py-10 text-center text-sm text-slate-500">No qualified booking-source data in this range.</p></div></section>
                <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Campaign proof</p><h2 class="mt-1 text-xl font-semibold">Promotion performance</h2></div><div class="divide-y divide-slate-100"><div v-for="promotion in report.promotions.slice(0, 8)" :key="promotion.id" class="grid grid-cols-[1fr_auto] gap-4 px-5 py-4 sm:px-6"><div><p class="font-medium text-slate-900">{{ promotion.title }}</p><p class="mt-1 text-xs text-slate-400">{{ promotion.venue }} · {{ number(promotion.impressions) }} impressions · {{ number(promotion.clicks) }} clicks</p></div><div class="text-right"><p class="font-semibold">{{ money(promotion.revenue) }}</p><p class="mt-1 text-xs text-slate-400">{{ number(promotion.bookings) }} bookings</p></div></div><p v-if="!report.promotions.length" class="px-6 py-10 text-center text-sm text-slate-500">No promotion activity in this range.</p></div></section>
            </div>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Existing owner proof</p><h2 class="mt-1 text-xl font-semibold">Organization performance</h2></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3 sm:px-6">Organization</th><th class="px-5 py-3 text-right">Profile views</th><th class="px-5 py-3 text-right">Bookings</th><th class="px-5 py-3 text-right sm:px-6">Confirmed value</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="organization in report.organizations" :key="organization.name"><td class="px-5 py-4 font-medium text-slate-900 sm:px-6">{{ organization.name }}</td><td class="px-5 py-4 text-right">{{ number(organization.profile_views) }}</td><td class="px-5 py-4 text-right">{{ number(organization.bookings) }}</td><td class="px-5 py-4 text-right font-semibold sm:px-6">{{ money(organization.revenue) }}</td></tr><tr v-if="!report.organizations.length"><td colspan="4" class="px-6 py-10 text-center text-slate-500">No organization activity in this range.</td></tr></tbody></table></div></section>

            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-xs leading-5 text-slate-500 sm:p-6"><strong class="text-slate-700">Data-use note:</strong> visitor counts are anonymous session-based estimates. Funnel stages are aggregate signals and should not be represented as a single-user cohort. Only confirmed marketplace bookings with acceptable payment states contribute to booking value. No-inventory and no-availability searches are demand signals, not guaranteed revenue. Local demo events are excluded. Range interpreted in {{ timezone }}.</section>
        </div>
    </PlatformLayout>
</template>
