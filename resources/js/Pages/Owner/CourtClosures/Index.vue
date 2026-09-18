<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ closures: Array });
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function reopen(closure) {
    if (!window.confirm(`Reopen the courts in ${closure.reference}? Cancelled bookings and refunds will stay unchanged.`)) return;
    router.patch(`/owner/court-closures/${closure.id}/reopen`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Emergency closures" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl space-y-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Safety and operations</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Emergency closures</h1><p class="mt-2 text-sm text-slate-500">Track closed courts, player cancellations, and platform-controlled online refund batches.</p></div><Link href="/owner/court-closures/create" class="rounded-xl bg-red-700 px-5 py-3 text-center text-sm font-semibold text-white">Create emergency closure</Link></div>
            <section v-if="closures.length" class="space-y-5">
                <article v-for="closure in closures" :id="`closure-${closure.id}`" :key="closure.id" class="app-card scroll-mt-6 overflow-hidden">
                    <div class="grid gap-4 border-b border-slate-100 p-6 lg:grid-cols-[1fr_auto]"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-semibold">{{ closure.reference }}</h2><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ closure.status_label }}</span><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ closure.refund_status_label }}</span></div><p class="mt-2 text-sm text-slate-600">{{ closure.courts.join(', ') }}<span v-if="closure.venue"> · {{ closure.venue }}</span></p><p class="mt-1 text-sm text-slate-500">{{ closure.starts_at }} → {{ closure.ends_at || 'until manually reopened' }}</p><p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-sm"><strong>Reason:</strong> {{ closure.reason }}</p></div><div class="lg:text-right"><p class="text-sm"><strong>{{ closure.booking_count }}</strong> cancelled · <strong>{{ closure.refund_count }}</strong> online refunds</p><p v-if="closure.manual_refund_count" class="mt-1 text-sm font-semibold text-amber-700">{{ closure.manual_refund_count }} pay-at-venue refund(s) for your team</p><p class="mt-1 text-sm text-slate-500">{{ money.format(Number(closure.refund_total)) }} online refund total</p><button v-if="closure.can_reopen" class="mt-4 rounded-xl border border-court-700 px-4 py-2 text-sm font-semibold text-court-800" @click="reopen(closure)">Reopen courts</button></div></div>
                    <details v-if="closure.bookings.length" class="group"><summary class="cursor-pointer list-none px-6 py-4 text-sm font-semibold text-court-800">Affected bookings ({{ closure.bookings.length }})</summary><div class="divide-y divide-slate-100 border-t border-slate-100"><div v-for="booking in closure.bookings" :key="booking.reference" class="grid gap-2 px-6 py-4 sm:grid-cols-[1fr_auto]"><div><strong class="text-sm">{{ booking.customer_name }} · {{ booking.reference }}</strong><p class="mt-1 text-xs text-slate-500">{{ booking.venue }} · {{ booking.resource }} · {{ booking.start }}</p></div><div class="text-sm font-semibold text-slate-600">{{ booking.status_label }}<p v-if="booking.failure_message" class="mt-1 max-w-sm text-xs font-normal text-red-700">{{ booking.failure_message }}</p></div></div></div></details>
                </article>
            </section>
            <section v-else class="app-card px-6 py-16 text-center"><h2 class="text-lg font-semibold">No emergency closures</h2><p class="mt-2 text-sm text-slate-500">Your normal schedule and availability blocks remain unchanged.</p></section>
        </div>
    </OwnerLayout>
</template>
