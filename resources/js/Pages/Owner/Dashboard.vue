<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '../../Layouts/OwnerLayout.vue';

const props = defineProps({ organization: Object, inventory: Object, today: Object, marketplace: Object, period: Object, promotions: Array, growth: Object });
const money = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(Number(value));
const priorityLabel = (priority) => ({ high: 'Important', medium: 'Worth trying', low: 'When you have time' }[priority] || priority);

const metrics = () => [
    { label: 'Venue page visits', value: props.marketplace.profile_views, detail: 'People opened your venue page', icon: 'eye' },
    { label: 'Bookings started', value: props.marketplace.booking_starts, detail: 'Last 30 days', icon: 'calendar' },
    { label: 'Confirmed bookings', value: props.marketplace.completed_bookings, detail: `${props.marketplace.conversion_rate}% of visits became bookings`, icon: 'check' },
    { label: 'Value of confirmed bookings', value: money(props.marketplace.booking_revenue), detail: 'Cancelled and refunded bookings are left out', icon: 'wallet' },
    { label: 'First-time players', value: props.marketplace.new_customers, detail: 'First confirmed booking', icon: 'users' },
];
</script>

<template>
    <Head title="Owner home" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div><p class="eyebrow">Today</p><h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Owner home</h1><p class="mt-2 text-sm text-slate-500">A quick look at {{ organization.name }}.</p></div>
                <div class="flex flex-wrap gap-2"><Link href="/owner/bookings/create" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Create booking</Link><Link href="/owner/venues" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Manage courts</Link></div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <section v-for="metric in metrics()" :key="metric.label" class="metric-card"><div class="flex items-start justify-between gap-3"><span class="metric-icon"><svg v-if="metric.icon === 'eye'" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" /><circle cx="12" cy="12" r="2.5" /></svg><svg v-else-if="metric.icon === 'calendar'" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3v3m12-3v3M4 9h16M5 5h14v15H5z" /></svg><svg v-else-if="metric.icon === 'check'" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16 9" /></svg><svg v-else-if="metric.icon === 'wallet'" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h15v13H4zM4 9h16m-5 4h5v3h-5z" /></svg><svg v-else viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm11 9a7 7 0 0 0-14 0m10-9a3 3 0 1 0 0-6" /></svg></span><span class="text-[10px] font-semibold uppercase tracking-wider text-slate-300">Last 30 days</span></div><p class="mt-5 text-sm font-medium text-slate-500">{{ metric.label }}</p><p class="mt-1 text-3xl font-semibold tracking-tight text-slate-950">{{ metric.value }}</p><p class="mt-2 text-xs text-slate-400">{{ metric.detail }}</p></section>
            </div>

            <section class="app-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="eyebrow">Helpful next steps</p><h3 class="mt-1 text-xl font-semibold">Ways to get more bookings</h3><p class="mt-2 text-sm text-slate-500">Simple suggestions based on player searches, bookings, deals, and past players.</p></div><Link href="/owner/growth" class="text-sm font-semibold text-court-700">View all →</Link></div>
                <div v-if="growth.active.length" class="grid gap-px bg-slate-100 lg:grid-cols-3"><div v-for="recommendation in growth.active" :key="recommendation.key" class="bg-white p-5 sm:p-6"><div class="flex items-center gap-2"><span :class="recommendation.priority === 'high' ? 'bg-amber-50 text-amber-800' : 'bg-court-50 text-court-800'" class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider">{{ priorityLabel(recommendation.priority) }}</span><span class="text-xs text-slate-400">{{ recommendation.venue || recommendation.type_label }}</span></div><h4 class="mt-4 font-semibold leading-6 text-slate-900">{{ recommendation.title }}</h4><p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ recommendation.explanation }}</p><Link :href="recommendation.suggested_action.url" class="mt-4 inline-block text-sm font-semibold text-court-700">{{ recommendation.suggested_action.label }} →</Link></div></div>
                <div v-else class="px-6 py-12 text-center"><p class="font-semibold text-slate-800">No suggestions yet</p><p class="mt-2 text-sm text-slate-500">FinACourt waits until there is enough real activity before suggesting something.</p></div>
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <section class="app-card overflow-hidden"><div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="eyebrow">Today</p><h3 class="mt-1 text-xl font-semibold">Today’s schedule</h3></div><div class="flex gap-2"><span class="rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800">{{ today.bookings }} bookings</span><span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">{{ today.pending_payments }} pending payments</span></div></div>
                    <div v-if="today.schedule.length" class="divide-y divide-slate-100"><div v-for="booking in today.schedule" :key="booking.id" class="grid gap-3 px-5 py-4 sm:grid-cols-[5rem_1fr_auto] sm:items-center sm:px-6"><div><p class="text-lg font-semibold text-slate-950">{{ booking.time }}</p><p class="text-xs capitalize text-slate-400">{{ booking.status }}</p></div><div><p class="font-medium text-slate-900">{{ booking.customer_name }}</p><p class="mt-1 text-sm text-slate-500">{{ booking.venue }} · {{ booking.resource }}</p></div><div class="sm:text-right"><p class="font-semibold text-slate-900">{{ money(booking.total_amount) }}</p><p class="mt-1 text-xs text-slate-400">{{ booking.payment_status }}</p></div></div></div>
                    <div v-else class="px-6 py-14 text-center"><p class="font-semibold text-slate-800">No bookings today</p><p class="mt-2 text-sm text-slate-500">The schedule is clear for {{ today.date }}.</p></div>
                    <div class="border-t border-slate-100 px-6 py-4"><Link :href="`/owner/bookings?date=${today.date}`" class="text-sm font-semibold text-court-700">View bookings →</Link></div>
                </section>

                <section class="app-card p-5 sm:p-6"><p class="eyebrow">Your places</p><h3 class="mt-1 text-xl font-semibold">Venues and courts</h3><div class="mt-6 grid grid-cols-3 gap-3 text-center"><div class="rounded-xl bg-slate-50 p-4"><p class="text-3xl font-semibold">{{ inventory.venues }}</p><p class="mt-1 text-xs text-slate-400">Venues</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-3xl font-semibold">{{ inventory.courts }}</p><p class="mt-1 text-xs text-slate-400">Courts</p></div><div class="rounded-xl bg-court-50 p-4"><p class="text-3xl font-semibold text-court-800">{{ inventory.active_courts }}</p><p class="mt-1 text-xs text-court-700">Bookable</p></div></div><div class="court-visual mt-6 h-32 rounded-2xl"></div><Link href="/owner/venues" class="mt-5 block rounded-xl border border-court-200 px-4 py-3 text-center text-sm font-semibold text-court-800">Manage venues and courts</Link></section>
            </div>

            <section class="app-card overflow-hidden"><div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6"><div><p class="eyebrow">Deals</p><h3 class="mt-1 text-xl font-semibold">How your deals are doing</h3></div><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">View all →</Link></div><div v-if="promotions.length" class="grid divide-y divide-slate-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0"><div v-for="promotion in promotions" :key="promotion.id" class="p-5 sm:p-6"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ promotion.title }}</p><p class="mt-1 text-xs text-slate-400">{{ promotion.venue }}</p></div><span class="rounded-lg bg-court-50 px-2 py-1 text-xs font-semibold text-court-800">{{ promotion.bookings }} bookings</span></div><p class="mt-5 text-2xl font-semibold">{{ money(promotion.revenue) }}</p><p class="mt-1 text-xs text-slate-400">Value of bookings · {{ promotion.impressions }} times shown · {{ promotion.clicks }} opened</p></div></div><p v-else class="px-6 py-12 text-center text-sm text-slate-500">No deal results in this period yet.</p></section>
        </div>
    </OwnerLayout>
</template>
