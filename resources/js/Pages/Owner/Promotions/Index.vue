<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ promotions: Array, pagination: Object, opportunities: Array });

function opportunityUrl(slot) {
    const query = new URLSearchParams({
        resource: slot.resource_id,
        date: slot.slot_date,
        start: slot.starts_at_time,
        end: slot.ends_at_time,
    });

    return `/owner/promotions/create?${query.toString()}`;
}
</script>

<template>
    <Head title="Deals" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold text-court-700">Fill open court times</p><h2 class="mt-1 text-3xl font-semibold tracking-tight">Deals</h2><p class="mt-2 text-sm text-slate-500">Create special offers for venues or court times you want players to book.</p></div><Link href="/owner/promotions/create" class="rounded-xl bg-court-700 px-4 py-2.5 text-center text-sm font-semibold text-white">Create deal</Link></div>
            <section v-if="opportunities.length" class="mt-7 overflow-hidden rounded-2xl border border-court-200 bg-court-950 text-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-white/10 p-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-300">Open soon</p><h3 class="mt-2 text-xl font-semibold">Court times you can turn into deals</h3><p class="mt-1 text-sm text-slate-300">Based on your opening hours and current bookings. Nothing is shown to players until you approve it.</p></div><span class="text-sm text-court-200">{{ opportunities.length }} suggestions</span></div>
                <div class="flex gap-3 overflow-x-auto p-4">
                    <Link v-for="slot in opportunities" :key="`${slot.resource_id}-${slot.slot_date}-${slot.starts_at_time}`" :href="opportunityUrl(slot)" class="min-w-64 rounded-xl bg-white p-4 text-slate-900"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ slot.resource_name }}</p><p class="mt-1 text-xs text-slate-500">{{ slot.venue_name }} · {{ slot.sport_name }}</p></div><span v-if="slot.is_last_minute" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Last minute</span></div><p class="mt-4 text-sm font-semibold">{{ slot.slot_date }} · {{ slot.starts_at_time }}–{{ slot.ends_at_time }}</p><p class="mt-1 text-xs text-slate-500">Normal price ₱{{ slot.estimated_value }}</p><span class="mt-4 inline-block text-xs font-semibold text-court-700">Create deal from this time →</span></Link>
                </div>
            </section>
            <div v-if="promotions.length" class="mt-7 grid gap-4 md:grid-cols-2">
                <Link v-for="promotion in promotions" :key="promotion.id" :href="`/owner/promotions/${promotion.id}`" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-court-300">
                    <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">{{ promotion.goal_label }}</p><h3 class="mt-2 text-xl font-semibold">{{ promotion.title }}</h3><p class="mt-1 text-sm text-slate-500">{{ promotion.venue }}<span v-if="promotion.resource"> · {{ promotion.resource }}</span></p></div><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', promotion.effective_status === 'active' ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600']">{{ promotion.effective_status_label }}</span></div>
                    <p class="mt-4 text-sm text-slate-600">{{ promotion.offer_label || 'Shown without a discount' }} · {{ promotion.starts_on }} to {{ promotion.ends_on }}<span v-if="promotion.slots_count"> · {{ promotion.slots_count }} court times</span></p>
                    <div class="mt-5 grid grid-cols-4 gap-2 border-t border-slate-100 pt-4 text-center text-xs"><div><strong class="block text-base text-slate-900">{{ promotion.impressions_count }}</strong><span class="text-slate-400">Shown</span></div><div><strong class="block text-base text-slate-900">{{ promotion.clicks_count }}</strong><span class="text-slate-400">Opened</span></div><div><strong class="block text-base text-slate-900">{{ promotion.booking_starts_count }}</strong><span class="text-slate-400">Started</span></div><div><strong class="block text-base text-slate-900">{{ promotion.bookings_count }}</strong><span class="text-slate-400">Booked</span></div></div>
                </Link>
            </div>
            <div v-else class="mt-7 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><h3 class="font-semibold">No deals yet</h3><p class="mt-2 text-sm text-slate-500">Create a deal for a venue or court time players can book.</p></div>
            <nav v-if="pagination.last_page > 1" class="mt-7 flex items-center justify-between gap-4" aria-label="Deal pages"><Link v-if="pagination.previous" :href="pagination.previous" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">← Previous</Link><span v-else></span><span class="text-sm text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span><Link v-if="pagination.next" :href="pagination.next" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Next →</Link><span v-else></span></nav>
        </div>
    </OwnerLayout>
</template>
