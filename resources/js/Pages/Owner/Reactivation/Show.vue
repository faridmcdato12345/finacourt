<script setup>
import { Head, Link, router } from '@inertiajs/vue3';

import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ campaign: Object });
function send() {
    if (window.confirm('Send this one-time in-app message to eligible opted-in prior customers?')) router.post(`/owner/reactivation/${props.campaign.id}/send`);
}
function cancel() { if (window.confirm('Cancel this draft?')) router.patch(`/owner/reactivation/${props.campaign.id}/cancel`); }
</script>

<template>
    <Head :title="campaign.title" />
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <Link href="/owner/reactivation" class="text-sm font-semibold text-court-700">← Customer reactivation</Link>
            <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ campaign.status_label }}</span><span class="text-xs text-slate-400">In-app only</span></div><h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ campaign.title }}</h2><p class="mt-2 text-sm text-slate-500">{{ campaign.venue }}<span v-if="campaign.sport"> · {{ campaign.sport }}</span> · {{ campaign.segment_label }}</p></div><div v-if="campaign.status === 'draft'" class="flex gap-2"><button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600" @click="cancel">Cancel</button><button class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="send">Send campaign</button></div></div>
            <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Player message</p><p class="mt-3 text-lg leading-7 text-slate-700">{{ campaign.message }}</p><div v-if="campaign.status === 'draft'" class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">Saving did not contact anyone. Sending will build the audience from this tenant's completed bookings, suppress non-consenting players, and enforce the contact cooldown.</div></section>
            <section v-if="campaign.status === 'sent'" class="mt-7 grid gap-3 sm:grid-cols-5"><div v-for="metric in [['Audience', campaign.audience], ['Sent', campaign.sent], ['Delivered', campaign.delivered], ['Suppressed', campaign.suppressed], ['Clicks', campaign.clicks]]" :key="metric[0]" class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm"><strong class="text-2xl">{{ metric[1] }}</strong><p class="mt-1 text-xs text-slate-400">{{ metric[0] }}</p></div></section>
        </div>
    </OwnerLayout>
</template>
