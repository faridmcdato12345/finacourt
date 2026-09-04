<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import GrowthOpportunityCard from '../../../Components/GrowthOpportunityCard.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';
import { groupOpportunities, relativeUpdatedAt } from '../../../lib/growth-opportunities.js';

const props = defineProps({ report: Object, snoozeOptions: Array });
const refreshing = ref(false);

const activeOpportunities = computed(() => props.report?.active || []);
const featuredOpportunity = computed(() => activeOpportunities.value[0] || null);
const opportunitySections = computed(() => groupOpportunities(activeOpportunities.value.slice(1)));
const opportunityCount = computed(() => activeOpportunities.value.length);
const updatedLabel = computed(() => relativeUpdatedAt(props.report?.calculated_at));

function setState(recommendation, status, snoozeDays = null) {
    router.post(`/owner/growth/${recommendation.key}/state`, {
        status,
        snooze_days: status === 'snoozed' ? snoozeDays : null,
    }, { preserveScroll: true });
}

function restore(recommendation) {
    router.delete(`/owner/growth/${recommendation.key}/state`, { preserveScroll: true });
}

function refreshOpportunities() {
    refreshing.value = true;
    router.reload({
        only: ['report'],
        preserveScroll: true,
        onFinish: () => { refreshing.value = false; },
    });
}
</script>

<template>
    <Head title="Growth opportunities" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-8">
            <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="eyebrow">Growth opportunities</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Your next steps to get more bookings</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Start with the first recommendation. Every opportunity is based on your real availability, marketplace activity, bookings, or past customers.</p>
                </div>
                <div class="flex items-center gap-3 self-start rounded-2xl border border-slate-200 bg-white px-4 py-3 lg:self-auto">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ opportunityCount }} current {{ opportunityCount === 1 ? 'opportunity' : 'opportunities' }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">Updated {{ updatedLabel }}</p>
                    </div>
                    <button type="button" :disabled="refreshing" class="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-600 hover:border-court-300 hover:text-court-800 disabled:cursor-wait disabled:opacity-50" aria-label="Refresh growth opportunities" @click="refreshOpportunities">
                        <svg viewBox="0 0 24 24" :class="['size-5', refreshing && 'animate-spin']" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 6v5h-5M4 18v-5h5" /><path d="M18.5 9A7 7 0 0 0 6.7 5.7L4 8m16 8-2.7 2.3A7 7 0 0 1 5.5 15" /></svg>
                    </button>
                </div>
            </header>

            <template v-if="featuredOpportunity">
                <section aria-labelledby="best-next-action">
                    <h2 id="best-next-action" class="sr-only">Best next action</h2>
                    <GrowthOpportunityCard :recommendation="featuredOpportunity" :snooze-options="snoozeOptions" featured @change-state="setState" />
                </section>

                <section v-for="section in opportunitySections" :key="section.key" class="space-y-4" :aria-labelledby="`opportunity-${section.key}`">
                    <div>
                        <h2 :id="`opportunity-${section.key}`" class="text-xl font-semibold tracking-tight text-slate-950">{{ section.title }}</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">{{ section.description }}</p>
                    </div>
                    <div class="grid gap-5 xl:grid-cols-2">
                        <GrowthOpportunityCard v-for="recommendation in section.recommendations" :key="recommendation.key" :recommendation="recommendation" :snooze-options="snoozeOptions" @change-state="setState" />
                    </div>
                </section>
            </template>

            <section v-else class="app-card px-6 py-16 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-court-50 text-xl text-court-700">✓</div>
                <h2 class="mt-4 text-xl font-semibold text-slate-900">No growth opportunities right now</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">FinACourt waits for enough real activity before recommending an action. Keep your court availability and venue details current while new marketplace activity comes in.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-4">
                    <Link href="/owner/analytics" class="text-sm font-semibold text-court-700">View visits and bookings →</Link>
                    <Link href="/owner/promotions" class="text-sm font-semibold text-court-700">Manage promotions →</Link>
                </div>
            </section>

            <details v-if="report.suppressed.length" class="app-card group overflow-visible">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 sm:px-6 [&::-webkit-details-marker]:hidden">
                    <div>
                        <p class="eyebrow">Past choices</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Dismissed and snoozed opportunities</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ report.suppressed.length }} hidden {{ report.suppressed.length === 1 ? 'opportunity' : 'opportunities' }}</p>
                    </div>
                    <span class="grid size-9 place-items-center rounded-full bg-slate-100 text-slate-600 transition group-open:rotate-180">⌄</span>
                </summary>
                <div class="divide-y divide-slate-100 border-t border-slate-100">
                    <div v-for="recommendation in report.suppressed" :key="recommendation.key" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div>
                            <p class="font-semibold text-slate-900">{{ recommendation.title }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ recommendation.state_label }}<span v-if="recommendation.snoozed_until"> until {{ new Date(recommendation.snoozed_until).toLocaleString() }}</span></p>
                        </div>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-court-700 hover:border-court-300" @click="restore(recommendation)">Show again</button>
                    </div>
                </div>
            </details>

            <details class="rounded-2xl border border-slate-200 bg-slate-50 text-sm text-slate-600">
                <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-slate-800 [&::-webkit-details-marker]:hidden">How recommendations work <span class="ml-1 text-court-700">+</span></summary>
                <p class="border-t border-slate-200 px-5 py-4 text-xs leading-5">Recommendations refresh from real platform activity. Search breakdowns appear only after enough different people have searched. Booking values exclude cancelled bookings, failed payments, and refunds. FinACourt never launches a promotion or contacts a customer unless you choose the action.</p>
            </details>
        </div>
    </OwnerLayout>
</template>
