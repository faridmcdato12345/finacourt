<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ date: String, timezone: String, bookings: Array });
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const summary = computed(() => ({
    total: props.bookings.length,
    confirmed: props.bookings.filter((booking) => booking.status === 'confirmed').length,
    holds: props.bookings.filter((booking) => booking.status === 'hold').length,
    paid: props.bookings.filter((booking) => booking.payment_status_value === 'paid').length,
    value: props.bookings.filter((booking) => booking.status !== 'cancelled' && booking.status !== 'expired').reduce((sum, booking) => sum + Number(booking.total_amount), 0),
}));
const schedule = computed(() => Object.values(props.bookings.reduce((groups, booking) => {
    const key = `${booking.venue}|${booking.resource}`;
    groups[key] ||= { key, venue: booking.venue, resource: booking.resource, sport: booking.sport, bookings: [] };
    groups[key].bookings.push(booking);
    return groups;
}, {})));

function changeDate(event) { router.get('/owner/bookings', { date: event.target.value }, { preserveState: true }); }
function cancelBooking(booking) { const reason = window.prompt(`Why is ${booking.reference} being cancelled? (optional)`, ''); if (reason !== null) router.patch(`/owner/bookings/${booking.id}/cancel`, { cancellation_reason: reason || null }); }
function updatePayment(booking, status) { const labels = { paid: 'mark this payment paid', failed: 'record payment failure', cancelled: 'cancel this payment', refunded: 'record a full manual refund' }; const note = window.prompt(`Add a note to ${labels[status]}${status === 'refunded' ? ' (required)' : ' (optional)'}.`, ''); if (note !== null && (status !== 'refunded' || note.trim())) router.patch(`/owner/bookings/${booking.id}/payment`, { status, note: note || null }); }
function statusClass(status) { return { confirmed: 'bg-court-100 text-court-800 border-court-200', hold: 'bg-amber-100 text-amber-800 border-amber-200', cancelled: 'bg-red-50 text-red-700 border-red-100', expired: 'bg-slate-100 text-slate-500 border-slate-200' }[status] || 'bg-slate-100 text-slate-600 border-slate-200'; }
</script>

<template>
    <Head title="Bookings" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Today</p><h2 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Bookings</h2><p class="mt-2 text-sm text-slate-500">All times shown in {{ timezone }}.</p></div><Link href="/owner/bookings/create" class="rounded-xl bg-court-700 px-5 py-3 text-center text-sm font-semibold text-white">+ Create booking</Link></div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"><section class="metric-card"><p class="text-sm text-slate-500">Today’s bookings</p><p class="mt-3 text-3xl font-semibold">{{ summary.total }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Confirmed</p><p class="mt-3 text-3xl font-semibold text-court-700">{{ summary.confirmed }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Held for now</p><p class="mt-3 text-3xl font-semibold text-amber-700">{{ summary.holds }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Paid bookings</p><p class="mt-3 text-3xl font-semibold">{{ summary.paid }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Booked court value</p><p class="mt-3 text-3xl font-semibold">{{ money.format(summary.value) }}</p></section></div>

            <section class="app-card overflow-hidden"><div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="eyebrow">Court schedule</p><h3 class="mt-1 text-xl font-semibold">{{ date }}</h3></div><label><span class="sr-only">Schedule date</span><input type="date" :value="date" class="rounded-xl border-slate-300 text-sm" @change="changeDate"></label></div>
                <div v-if="schedule.length" class="overflow-x-auto"><div class="min-w-[48rem]"><div v-for="row in schedule" :key="row.key" class="grid grid-cols-[11rem_1fr] border-b border-slate-100 last:border-0"><div class="border-r border-slate-100 px-5 py-4"><p class="font-semibold text-slate-900">{{ row.resource }}</p><p class="mt-1 text-xs text-slate-400">{{ row.venue }} · {{ row.sport }}</p></div><div class="flex gap-2 overflow-x-auto bg-[linear-gradient(90deg,rgba(241,245,249,.55)_1px,transparent_1px)] bg-[size:12.5%_100%] px-4 py-3"><div v-for="booking in row.bookings" :key="booking.id" :class="['min-w-40 rounded-xl border px-3 py-2.5', statusClass(booking.status)]"><p class="text-xs font-semibold">{{ booking.start_time }}–{{ booking.end_time }}</p><p class="mt-1 truncate text-xs">{{ booking.customer_name }}</p></div></div></div></div></div>
                <div v-else class="px-6 py-14 text-center"><p class="font-semibold">No bookings on this date</p><p class="mt-2 text-sm text-slate-500">Create a phone, Messenger, or walk-in reservation.</p></div>
            </section>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 px-5 py-5 sm:px-6"><h3 class="text-xl font-semibold">Booking details <span class="text-slate-400">({{ bookings.length }})</span></h3></div><div v-if="bookings.length" class="divide-y divide-slate-100"><article v-for="booking in bookings" :key="booking.id" class="grid gap-4 px-5 py-5 lg:grid-cols-[6rem_1fr_auto] lg:items-start lg:px-6"><div class="rounded-xl bg-slate-950 px-3 py-3 text-center text-white"><p class="text-lg font-semibold">{{ booking.start_time }}</p><p class="mt-1 text-xs text-slate-400">to {{ booking.end_time }}</p></div><div><div class="flex flex-wrap items-center gap-2"><h4 class="font-semibold">{{ booking.customer_name }}</h4><span :class="['rounded-full border px-2.5 py-1 text-xs font-semibold', statusClass(booking.status)]">{{ booking.status_label }}</span></div><p class="mt-1 text-sm text-slate-600">{{ booking.venue }} · {{ booking.resource }} · {{ booking.sport }}</p><p class="mt-2 text-xs text-slate-400">{{ booking.reference }} · {{ booking.source }}<span v-if="booking.created_by"> · added by {{ booking.created_by }}</span></p><p v-if="booking.customer_phone || booking.customer_email" class="mt-2 text-xs text-slate-500"><span v-if="booking.customer_phone">{{ booking.customer_phone }}</span><span v-if="booking.customer_phone && booking.customer_email"> · </span><span>{{ booking.customer_email }}</span></p><p v-if="booking.promotion_title" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">{{ booking.promotion_title }} · saved {{ money.format(booking.discount_amount) }}</p><p v-if="booking.payment_requires_review" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">Please check payment: {{ booking.payment_review_reason }}</p></div><div class="flex flex-wrap items-center gap-2 lg:max-w-64 lg:justify-end"><div class="mr-auto lg:mr-0 lg:w-full lg:text-right"><p class="font-semibold">{{ money.format(booking.total_amount) }}</p><p class="mt-1 text-xs text-slate-400">Court price</p><p v-if="Number(booking.platform_service_fee_amount) > 0" class="mt-1 text-xs text-court-700">Player paid {{ money.format(booking.player_total_amount) }} including {{ money.format(booking.platform_service_fee_amount) }} FinACourt fee</p><p class="mt-1 text-xs text-slate-400">{{ booking.payment_mode || 'Payment type not set' }} · {{ booking.payment_status || 'Payment not recorded' }}</p></div><button v-if="booking.can_mark_paid" type="button" class="rounded-lg bg-court-50 px-3 py-2 text-xs font-semibold text-court-800" @click="updatePayment(booking, 'paid')">Mark paid</button><button v-if="booking.can_mark_failed" type="button" class="rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800" @click="updatePayment(booking, 'failed')">Payment failed</button><button v-if="booking.can_refund" type="button" class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700" @click="updatePayment(booking, 'refunded')">Record refund</button><button v-if="booking.can_cancel" type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50" @click="cancelBooking(booking)">Cancel</button></div></article></div><div v-else class="px-6 py-12 text-center text-sm text-slate-500">No bookings for this date.</div></section>
        </div>
    </OwnerLayout>
</template>
