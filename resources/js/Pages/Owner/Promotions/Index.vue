<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ promotions: Array, pagination: Object, opportunities: Array });

const liveCount = computed(() => props.promotions.filter(
    (promotion) => promotion.effective_status === 'active',
).length);
const draftCount = computed(() => props.promotions.filter(
    (promotion) => ['draft', 'paused'].includes(promotion.effective_status),
).length);

function opportunityUrl(slot) {
    const query = new URLSearchParams({
        resource: slot.resource_id,
        date: slot.slot_date,
        start: slot.starts_at_time,
        end: slot.ends_at_time,
    });

    return `/owner/promotions/create?${query.toString()}`;
}

function statusClass(status) {
    if (status === 'active') return 'bg-court-50 text-court-800';
    if (status === 'scheduled') return 'bg-blue-50 text-blue-700';
    if (status === 'draft') return 'bg-slate-100 text-slate-600';
    if (status === 'paused') return 'bg-amber-50 text-amber-800';

    return 'bg-slate-100 text-slate-500';
}

function money(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));
}
</script>

<template>
    <Head title="Promotions" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Fill unused court time</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight">Promotions</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Choose open court times, set an optional discount, and publish the offer to eligible players. Prices are checked again when a player books.</p>
                </div>
                <Link href="/owner/promotions/create" class="rounded-xl bg-court-700 px-5 py-3 text-center text-sm font-semibold text-white shadow-sm">Create a promotion →</Link>
            </header>

            <section class="mt-7 grid gap-4 sm:grid-cols-3" aria-label="Promotion summary">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Live now</p><p class="mt-2 text-3xl font-semibold">{{ liveCount }}</p><p class="mt-1 text-xs text-slate-500">Visible on this page</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Needs attention</p><p class="mt-2 text-3xl font-semibold">{{ draftCount }}</p><p class="mt-1 text-xs text-slate-500">Draft or paused on this page</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Open-time ideas</p><p class="mt-2 text-3xl font-semibold">{{ opportunities.length }}</p><p class="mt-1 text-xs text-slate-500">Calculated from real availability</p></div>
            </section>

            <section v-if="opportunities.length" class="mt-7 overflow-hidden rounded-3xl border border-court-200 bg-court-950 text-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-white/10 p-6 sm:flex-row sm:items-end sm:justify-between">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-court-300">Recommended next action</p><h3 class="mt-2 text-xl font-semibold">Turn an open court time into a promotion</h3><p class="mt-1 max-w-2xl text-sm leading-6 text-slate-300">These are real upcoming openings. Select one to prefill the guided promotion setup; nothing is published automatically.</p></div>
                    <span class="shrink-0 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-court-100">{{ opportunities.length }} suggestions</span>
                </div>
                <div class="court-carousel flex snap-x gap-3 overflow-x-auto p-4 sm:p-5">
                    <article v-for="slot in opportunities" :key="`${slot.resource_id}-${slot.slot_date}-${slot.starts_at_time}`" class="min-w-64 snap-start rounded-2xl bg-white p-4 text-slate-900">
                        <div class="flex items-start justify-between gap-3"><div><h4 class="font-semibold">{{ slot.resource_name }}</h4><p class="mt-1 text-xs text-slate-500">{{ slot.venue_name }} · {{ slot.sport_name }}</p></div><span v-if="slot.is_last_minute" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Soon</span></div>
                        <p class="mt-4 text-sm font-semibold">{{ slot.slot_date }} · {{ slot.starts_at_time }}–{{ slot.ends_at_time }}</p>
                        <p class="mt-1 text-xs text-slate-500">Normal value {{ money(slot.estimated_value) }}</p>
                        <Link :href="opportunityUrl(slot)" class="mt-4 block rounded-xl bg-court-50 px-3 py-2.5 text-center text-xs font-semibold text-court-800">Promote this time →</Link>
                    </article>
                </div>
            </section>

            <section class="mt-9">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your work</p><h3 class="mt-1 text-2xl font-semibold">Your promotions</h3><p class="mt-1 text-sm text-slate-500">Open a promotion to review its reach and bookings, or edit its offer and availability.</p></div><span v-if="promotions.length" class="text-sm text-slate-500">{{ promotions.length }} on this page</span></div>

                <div v-if="promotions.length" class="mt-5 grid gap-4 md:grid-cols-2">
                    <article v-for="promotion in promotions" :key="promotion.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">{{ promotion.promotion_type_label }}</p><h4 class="mt-2 text-xl font-semibold">{{ promotion.title }}</h4><p class="mt-1 text-sm text-slate-500">{{ promotion.venue }}<span v-if="promotion.resource"> · {{ promotion.resource }}</span></p></div><span :class="['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', statusClass(promotion.effective_status)]">{{ promotion.effective_status_label }}</span></div>
                        <div class="mt-4 rounded-xl bg-slate-50 px-4 py-3"><p class="font-semibold text-court-800">{{ promotion.offer_label || 'Normal price' }}</p><p class="mt-1 text-xs text-slate-500">{{ promotion.slots_count ? `${promotion.slots_count} exact court ${promotion.slots_count === 1 ? 'time' : 'times'}` : `${promotion.starts_on} through ${promotion.ends_on}` }}</p></div>
                        <dl class="mt-4 grid grid-cols-4 gap-2 text-center text-xs"><div><dt class="text-slate-400">Shown</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ promotion.impressions_count }}</dd></div><div><dt class="text-slate-400">Opened</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ promotion.clicks_count }}</dd></div><div><dt class="text-slate-400">Started</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ promotion.booking_starts_count }}</dd></div><div><dt class="text-slate-400">Booked</dt><dd class="mt-1 text-base font-semibold text-slate-900">{{ promotion.bookings_count }}</dd></div></dl>
                        <div class="mt-5 flex gap-2 border-t border-slate-100 pt-4"><Link :href="`/owner/promotions/${promotion.id}`" class="flex-1 rounded-xl bg-court-700 px-4 py-2.5 text-center text-sm font-semibold text-white">View results</Link><Link :href="`/owner/promotions/${promotion.id}/edit`" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Edit</Link></div>
                    </article>
                </div>
                <div v-else class="mt-5 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><h3 class="font-semibold">No promotions yet</h3><p class="mt-2 text-sm text-slate-500">Use the guided setup to publish your first court offer.</p><Link href="/owner/promotions/create" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Create your first promotion</Link></div>
            </section>

            <nav v-if="pagination.last_page > 1" class="mt-7 flex items-center justify-between gap-4" aria-label="Promotion pages"><Link v-if="pagination.previous" :href="pagination.previous" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">← Previous</Link><span v-else></span><span class="text-sm text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span><Link v-if="pagination.next" :href="pagination.next" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Next →</Link><span v-else></span></nav>
        </div>
    </OwnerLayout>
</template>
