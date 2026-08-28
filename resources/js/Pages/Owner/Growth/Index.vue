<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ report: Object, snoozeOptions: Array });
const snoozeDays = reactive({});
const number = (value) => new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(Number(value));
const label = (key) => String(key).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const display = (value) => typeof value === 'boolean' ? (value ? 'Yes' : 'No') : numberOrText(value);
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
    <Head title="Growth opportunities" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Evidence into action</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Growth opportunities</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Deterministic suggestions calculated from your real marketplace demand, inventory, bookings, campaigns, and customer history. Nothing runs without your confirmation.</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                    Last calculated <strong class="text-slate-800">{{ new Date(report.calculated_at).toLocaleString() }}</strong>
                </div>
            </div>

            <section v-if="report.active.length" class="grid gap-5 xl:grid-cols-2">
                <article v-for="recommendation in report.active" :key="recommendation.key" class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 p-5 sm:p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="recommendation.priority === 'high' ? 'bg-amber-50 text-amber-800' : recommendation.priority === 'medium' ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wider">{{ recommendation.priority }} priority</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-500">{{ recommendation.type_label }}</span>
                            <span v-if="recommendation.venue" class="text-xs text-slate-400">{{ recommendation.venue }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-semibold tracking-tight text-slate-950">{{ recommendation.title }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ recommendation.explanation }}</p>
                    </div>
                    <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-end">
                        <dl class="grid gap-2 sm:grid-cols-2">
                            <div v-for="(value, key) in recommendation.evidence" :key="key" class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ label(key) }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-800">{{ display(value) }}</dd>
                            </div>
                        </dl>
                        <Link :href="recommendation.suggested_action.url" class="rounded-xl bg-court-700 px-4 py-3 text-center text-sm font-semibold text-white">{{ recommendation.suggested_action.label }} →</Link>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6">
                        <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'dismissed')">Dismiss</button>
                        <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'resolved')">Mark resolved</button>
                        <div class="ml-auto flex items-center gap-2">
                            <AppSelect v-model="snoozeDays[recommendation.key]" :options="snoozeOptions" size="sm" class="min-w-28 bg-white" />
                            <button type="button" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600" @click="setState(recommendation, 'snoozed')">Snooze</button>
                        </div>
                    </div>
                </article>
            </section>

            <section v-else class="app-card px-6 py-16 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-court-50 text-xl text-court-700">✓</div>
                <h2 class="mt-4 text-xl font-semibold text-slate-900">No supported opportunity yet</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">The engine will stay quiet until a rule has enough real evidence. It never invents benchmark statistics or sparse customer patterns.</p>
                <div class="mt-5 flex justify-center gap-3"><Link href="/owner/analytics" class="text-sm font-semibold text-court-700">Review analytics</Link><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">Review promotions</Link></div>
            </section>

            <section v-if="report.suppressed.length" class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Owner controls</p><h2 class="mt-1 text-xl font-semibold">Suppressed recommendations</h2><p class="mt-2 text-sm text-slate-500">Dismissed and resolved suggestions stay hidden until restored. Snoozed suggestions return automatically after their date.</p></div>
                <div class="divide-y divide-slate-100">
                    <div v-for="recommendation in report.suppressed" :key="recommendation.key" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div><p class="font-semibold text-slate-900">{{ recommendation.title }}</p><p class="mt-1 text-xs text-slate-400">{{ recommendation.state_label }}<span v-if="recommendation.snoozed_until"> until {{ new Date(recommendation.snoozed_until).toLocaleString() }}</span> · {{ recommendation.rule }}</p></div>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-court-700" @click="restore(recommendation)">Restore</button>
                    </div>
                </div>
            </section>

            <p class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-xs leading-5 text-slate-500">Recommendations expire after 24 hours and are recalculated from source facts. Demand cohorts retain the Phase 11 minimum privacy threshold. Qualified booking values exclude cancelled bookings and failed, cancelled, or refunded payment states.</p>
        </div>
    </OwnerLayout>
</template>
