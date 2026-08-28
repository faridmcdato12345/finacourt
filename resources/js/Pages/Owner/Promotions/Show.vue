<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ promotion: Object, public_url: String });

function removePromotion() {
    if (window.confirm(`Delete ${props.promotion.title}? Attributed promotions must be deactivated instead.`)) router.delete(`/owner/promotions/${props.promotion.id}`);
}
</script>

<template>
    <Head :title="promotion.title" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">← Promotions</Link><p class="mt-5 text-sm font-semibold uppercase tracking-wider text-court-700">Owner preview</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">{{ promotion.title }}</h2></div><div class="flex gap-2"><Link :href="`/owner/promotions/${promotion.id}/edit`" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Edit</Link><button class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="removePromotion">Delete</button></div></div>
            <div class="mt-7 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <section class="overflow-hidden rounded-3xl border border-court-200 bg-white shadow-sm"><div class="bg-court-950 p-7 text-white"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-court-300 px-3 py-1 text-xs font-semibold text-court-950">{{ promotion.offer_label || promotion.promotion_type_label }}</span><span class="rounded-full bg-white/10 px-3 py-1 text-xs">Preview</span></div><h3 class="mt-5 text-3xl font-semibold">{{ promotion.title }}</h3><p class="mt-3 max-w-2xl leading-7 text-slate-300">{{ promotion.description || 'No campaign description provided.' }}</p></div><div class="p-7"><p class="font-semibold">{{ promotion.venue }}<span v-if="promotion.resource"> · {{ promotion.resource }}</span></p><p class="mt-2 text-sm text-slate-500">Valid {{ promotion.starts_on }} through {{ promotion.ends_on }}<span v-if="promotion.starts_at_time"> · {{ promotion.starts_at_time }}–{{ promotion.ends_at_time }}</span></p><a :href="public_url" target="_blank" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Open public campaign ↗</a></div></section>
                <aside class="space-y-4"><section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Campaign</h3><p class="mt-3 text-sm font-semibold text-slate-800">{{ promotion.goal_label }}</p><p class="mt-2 text-sm text-slate-600">{{ promotion.effective_status_label }} · {{ promotion.is_public ? 'Public' : 'Private' }}</p><p class="mt-3 text-xs text-slate-400">Audience: {{ promotion.audience_sport || 'All venue sports' }} · {{ promotion.audience_city_slug }}</p><p class="mt-3 break-all text-xs text-slate-400">{{ promotion.campaign_token }}</p></section><section class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Performance hooks</h3><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between"><dt class="text-slate-500">Impressions</dt><dd class="font-semibold">{{ promotion.impressions_count }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Clicks</dt><dd class="font-semibold">{{ promotion.clicks_count }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Booking starts</dt><dd class="font-semibold">{{ promotion.booking_starts_count }}</dd></div><div class="flex justify-between"><dt class="text-slate-500">Attributed bookings</dt><dd class="font-semibold">{{ promotion.bookings_count }}</dd></div></dl></section></aside>
            </div>
            <section v-if="promotion.slots.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Targeted inventory</p><h3 class="mt-2 text-xl font-semibold">{{ promotion.slots.length }} promoted slots</h3></div><span class="text-xs text-slate-400">Stable slot identifiers ready for Phase 13</span></div><div class="mt-5 divide-y divide-slate-100"><div v-for="slot in promotion.slots" :key="slot.id" class="grid gap-2 py-4 text-sm sm:grid-cols-[1fr_1fr_1.3fr]"><div><p class="font-semibold">{{ slot.resource }}</p><p class="text-slate-500">{{ slot.sport }}</p></div><p class="font-semibold">{{ slot.slot_date }} · {{ slot.starts_at_time }}–{{ slot.ends_at_time }}</p><p class="break-all text-xs text-slate-400">{{ slot.slot_token }}</p></div></div></section>
        </div>
    </OwnerLayout>
</template>
