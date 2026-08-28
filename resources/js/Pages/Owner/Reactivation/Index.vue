<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ report: Object, segments: Object, rules: Object });
</script>

<template>
    <Head title="Customer reactivation" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="text-sm font-semibold text-court-700">Customer relationships</p><h2 class="mt-1 text-3xl font-semibold tracking-tight">Bring players back</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Create a one-time in-app message for players who previously booked with your business. Marketing consent and contact cooldowns are always enforced.</p></div>
                <Link href="/owner/reactivation/create" class="rounded-xl bg-court-700 px-4 py-2.5 text-center text-sm font-semibold text-white">Create comeback campaign</Link>
            </div>

            <section class="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Reactivation performance">
                <div v-for="metric in [
                    ['Audience', report.audience], ['Sent', report.sent], ['Delivered', report.delivered],
                    ['Clicks', report.clicks], ['Bookings', report.resulting_bookings], ['Revenue', `₱${report.resulting_revenue}`]
                ]" :key="metric[0]" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ metric[0] }}</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ metric[1] }}</p></div>
            </section>

            <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Eligible history</p><h3 class="mt-1 text-xl font-semibold">Available customer segments</h3></div><p class="text-xs text-slate-500">{{ rules.frequency_cooldown_days }}-day tenant contact cooldown</p></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><strong class="text-2xl">{{ segments.inactive_30 }}</strong><p class="mt-1 text-sm text-slate-500">No booking in 30 days</p></div><div class="rounded-xl bg-slate-50 p-4"><strong class="text-2xl">{{ segments.inactive_60 }}</strong><p class="mt-1 text-sm text-slate-500">No booking in 60 days</p></div><div class="rounded-xl bg-slate-50 p-4"><strong class="text-2xl">{{ segments.prior_weekday }}</strong><p class="mt-1 text-sm text-slate-500">Prior weekday players</p></div></div>
                <p class="mt-4 text-xs leading-5 text-slate-400">These counts include only account-linked players with a completed booking at this organization. Sending is further limited to explicit opt-ins; no marketplace-wide player data is exposed.</p>
            </section>

            <section class="mt-7">
                <h3 class="text-xl font-semibold">Campaigns</h3>
                <div v-if="report.campaigns.length" class="mt-4 grid gap-4 md:grid-cols-2">
                    <Link v-for="campaign in report.campaigns" :key="campaign.id" :href="`/owner/reactivation/${campaign.id}`" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-court-300">
                        <div class="flex items-start justify-between gap-4"><div><h4 class="font-semibold">{{ campaign.title }}</h4><p class="mt-1 text-sm text-slate-500">{{ campaign.venue }}</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">{{ campaign.status_label }}</span></div>
                        <div class="mt-5 grid grid-cols-4 gap-2 border-t border-slate-100 pt-4 text-center text-xs"><div><strong class="block text-base">{{ campaign.audience }}</strong><span class="text-slate-400">Audience</span></div><div><strong class="block text-base">{{ campaign.sent }}</strong><span class="text-slate-400">Sent</span></div><div><strong class="block text-base">{{ campaign.delivered }}</strong><span class="text-slate-400">Delivered</span></div><div><strong class="block text-base">{{ campaign.clicks }}</strong><span class="text-slate-400">Clicks</span></div></div>
                    </Link>
                </div>
                <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"><h4 class="font-semibold">No comeback campaigns yet</h4><p class="mt-2 text-sm text-slate-500">Draft one when you have a useful reason for past customers to return.</p></div>
            </section>
        </div>
    </OwnerLayout>
</template>
