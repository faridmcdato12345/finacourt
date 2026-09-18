<script setup>
import { Head, router } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

defineProps({ closures: Array });
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function approve(closure, retry = false) {
    const action = retry ? 'retry eligible failed refunds' : 'approve and submit all online refunds';
    if (!window.confirm(`Do you want to ${action} for ${closure.reference}?`)) return;
    router.post(`/platform/court-closures/${closure.id}/${retry ? 'retry' : 'approve'}`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Emergency closure refunds" />
    <PlatformLayout>
        <div class="space-y-7">
            <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Payments operations</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Emergency closure refund batches</h1><p class="mt-2 max-w-3xl text-sm text-slate-600">Review a closure once. Approval queues one idempotent full refund per paid online booking against FinACourt’s configured provider account.</p></div>
            <div v-if="$page.props.errors?.refunds" role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ $page.props.errors.refunds }}</div>
            <section v-if="closures.length" class="space-y-5">
                <article v-for="closure in closures" :id="`closure-${closure.id}`" :key="closure.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="grid gap-5 border-b border-slate-100 p-6 lg:grid-cols-[1fr_auto]"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold">{{ closure.organization }} · {{ closure.reference }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ closure.status_label }}</span><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ closure.refund_status_label }}</span></div><p class="mt-2 text-sm text-slate-600">{{ closure.courts.join(', ') }}<span v-if="closure.venue"> · {{ closure.venue }}</span></p><p class="mt-1 text-sm text-slate-500">{{ closure.starts_at }} → {{ closure.ends_at || 'until reopened' }}</p><p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm"><strong>Reason:</strong> {{ closure.reason }}</p><p class="mt-2 text-xs text-slate-400">Created by {{ closure.created_by || 'unknown' }}<span v-if="closure.approved_by"> · Approved by {{ closure.approved_by }}</span></p></div><div class="lg:min-w-60 lg:text-right"><p class="text-2xl font-semibold">{{ money.format(Number(closure.refund_total)) }}</p><p class="mt-1 text-sm text-slate-500">{{ closure.refund_count }} online refund(s) · {{ closure.booking_count }} cancellations</p><button v-if="closure.can_approve" class="mt-4 rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="approve(closure)">Approve refund batch</button><button v-if="closure.can_retry" class="mt-4 rounded-xl border border-court-700 px-4 py-2.5 text-sm font-semibold text-court-800" @click="approve(closure, true)">Retry eligible refunds</button></div></div>
                    <details class="group"><summary class="cursor-pointer list-none px-6 py-4 text-sm font-semibold text-court-800">Individual booking results ({{ closure.bookings.length }})</summary><div class="divide-y divide-slate-100 border-t border-slate-100"><div v-for="booking in closure.bookings" :key="booking.reference" class="grid gap-2 px-6 py-4 sm:grid-cols-[1fr_auto]"><div><strong class="text-sm">{{ booking.customer_name }} · {{ booking.reference }}</strong><p class="mt-1 text-xs text-slate-500">{{ booking.venue }} · {{ booking.resource }} · {{ booking.start }}<span v-if="booking.refund_reference"> · {{ booking.refund_reference }}</span></p></div><div class="sm:text-right"><p class="text-sm font-semibold">{{ booking.status_label }}</p><p v-if="Number(booking.amount)" class="text-xs text-slate-500">{{ money.format(Number(booking.amount)) }}</p><p v-if="booking.requires_review" class="mt-1 text-xs font-semibold text-red-700">Manual provider reconciliation required · <a href="/platform/payments" class="underline">open payments</a></p><p v-if="booking.failure_message" class="mt-1 max-w-sm text-xs text-red-700">{{ booking.failure_message }}</p></div></div></div></details>
                </article>
            </section>
            <section v-else class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center"><h2 class="text-lg font-semibold">No emergency closure batches</h2><p class="mt-2 text-sm text-slate-500">Owner-created closure batches will appear here.</p></section>
        </div>
    </PlatformLayout>
</template>
