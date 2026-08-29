<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({
    organizations: { type: Array, required: true },
    payouts: { type: Array, required: true },
    recentEntries: { type: Array, required: true },
    defaults: { type: Object, required: true },
});

const organizationOptions = props.organizations.map((organization) => ({ value: organization.id, label: organization.name }));
const prepare = useForm({ organization_id: organizationOptions[0]?.value || '', currency: props.defaults.currency, period_ended_at: props.defaults.through_date });
const correction = useForm({ organization_id: organizationOptions[0]?.value || '', amount: '', currency: props.defaults.currency, reason: '' });
const references = reactive({});
const reasons = reactive({});

const money = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));

function postAction(payout, action, data = {}) {
    router.post(`/platform/owner-payouts/${payout.id}/${action}`, data, { preserveScroll: true });
}
</script>

<template>
    <Head title="Owner payouts" />
    <PlatformLayout>
        <div class="space-y-8">
            <section><p class="eyebrow">Online court earnings</p><h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Owner payouts</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Prepare and record manual bank or GCash transfers for court prices collected through online checkout. FinACourt never sends money automatically from this page.</p></section>

            <section class="grid gap-6 xl:grid-cols-2">
                <form class="app-card p-5 sm:p-6" @submit.prevent="prepare.post('/platform/owner-payouts', { preserveScroll: true })">
                    <h2 class="text-xl font-semibold">Prepare an owner payout</h2><p class="mt-2 text-sm leading-6 text-slate-500">This gathers all ready, unpaid earnings and refund corrections through the selected date. Review it before approving.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="sm:col-span-2"><span class="text-sm font-medium">Court owner account</span><AppSelect v-model="prepare.organization_id" :options="organizationOptions" class="mt-2" /><span v-if="prepare.errors.organization_id" class="mt-1 block text-xs text-red-600">{{ prepare.errors.organization_id }}</span></label><label><span class="text-sm font-medium">Include earnings through</span><input v-model="prepare.period_ended_at" type="date" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="prepare.errors.period_ended_at" class="mt-1 block text-xs text-red-600">{{ prepare.errors.period_ended_at }}</span></label><label><span class="text-sm font-medium">Currency</span><input v-model="prepare.currency" maxlength="3" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 uppercase"></label></div>
                    <button :disabled="prepare.processing" class="mt-5 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 font-semibold text-white hover:bg-court-800 disabled:opacity-60">{{ prepare.processing ? 'Preparing…' : 'Prepare payout for review' }}</button>
                </form>

                <form class="app-card p-5 sm:p-6" @submit.prevent="correction.post('/platform/owner-payouts/adjustments', { preserveScroll: true, onSuccess: () => correction.reset('amount', 'reason') })">
                    <h2 class="text-xl font-semibold">Add a correction</h2><p class="mt-2 text-sm leading-6 text-slate-500">Use a positive amount to add money owed or a negative amount to deduct it. This adds a new record and never changes old history.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="sm:col-span-2"><span class="text-sm font-medium">Court owner account</span><AppSelect v-model="correction.organization_id" :options="organizationOptions" class="mt-2" /></label><label><span class="text-sm font-medium">Amount</span><input v-model="correction.amount" type="number" step="0.01" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Example: -100.00"><span v-if="correction.errors.amount" class="mt-1 block text-xs text-red-600">{{ correction.errors.amount }}</span></label><label><span class="text-sm font-medium">Currency</span><input v-model="correction.currency" maxlength="3" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 uppercase"></label><label class="sm:col-span-2"><span class="text-sm font-medium">Why is this needed?</span><textarea v-model="correction.reason" rows="2" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" /><span v-if="correction.errors.reason" class="mt-1 block text-xs text-red-600">{{ correction.errors.reason }}</span></label></div>
                    <button :disabled="correction.processing" class="mt-5 min-h-12 w-full rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-60">Add correction</button>
                </form>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="text-xl font-semibold">Court owner balances</h2><p class="mt-2 text-sm text-slate-500">Owners must save active payment details before a payout can be prepared.</p></div>
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Owner account</th><th class="px-5 py-3">Payment details</th><th class="px-5 py-3 text-right">Ready</th><th class="px-5 py-3 text-right">Waiting</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="organization in organizations" :key="organization.id"><td class="px-5 py-4 font-semibold">{{ organization.name }}</td><td class="px-5 py-4"><span :class="organization.payment_details_ready ? 'bg-court-50 text-court-800' : 'bg-amber-50 text-amber-800'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ organization.payment_details_ready ? `${organization.payment_method} · ${organization.payment_summary}` : 'Not added yet' }}</span></td><td class="px-5 py-4 text-right font-semibold">{{ money(organization.ready) }}</td><td class="px-5 py-4 text-right text-slate-600">{{ money(organization.waiting) }}</td></tr></tbody></table></div>
            </section>

            <section class="space-y-4">
                <div><h2 class="text-2xl font-semibold">Prepared payouts</h2><p class="mt-1 text-sm text-slate-500">Approve first, send money outside FinACourt, then record the transfer reference.</p></div>
                <article v-for="payout in payouts" :key="payout.id" class="app-card p-5 sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-lg font-semibold">{{ payout.organization }}</h3><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ payout.status_label }}</span><span v-if="payout.requested_by_owner" class="rounded-full bg-court-50 px-2.5 py-1 text-xs font-semibold text-court-800">Requested by owner</span></div><p class="mt-1 text-sm text-slate-500">{{ payout.reference }} · {{ payout.period }} · {{ payout.entries_count }} entries</p><p v-if="payout.requested_by_owner" class="mt-1 text-xs text-slate-500">Requested by {{ payout.requested_by }} on {{ payout.requested_at }}</p><p class="mt-3 text-sm text-slate-700">Send by {{ payout.destination.method_label }} to <strong>{{ payout.destination.account_name }}</strong></p><p v-if="payout.destination.details.bank_name" class="mt-1 text-sm text-slate-600">{{ payout.destination.details.bank_name }} · {{ payout.destination.details.account_number }}</p><p v-if="payout.destination.details.mobile_number" class="mt-1 text-sm text-slate-600">{{ payout.destination.details.mobile_number }}</p><p v-if="payout.destination.details.instructions" class="mt-1 text-sm text-slate-600">{{ payout.destination.details.instructions }}</p><p v-if="payout.external_reference" class="mt-2 text-xs text-slate-400">Transfer reference: {{ payout.external_reference }}</p></div><div class="lg:text-right"><p class="text-2xl font-semibold">{{ money(payout.amount) }}</p><a :href="`/platform/owner-payouts/${payout.id}/statement`" class="mt-2 inline-block text-sm font-semibold text-court-700 hover:underline">Download statement</a></div></div>
                    <div v-if="payout.can_approve" class="mt-5"><button class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="postAction(payout, 'approve')">Approve payout</button></div>
                    <div v-if="payout.can_send" class="mt-5 grid gap-3 rounded-2xl bg-court-50 p-4 sm:grid-cols-[1fr_auto]"><label><span class="text-xs font-semibold uppercase tracking-wider text-court-800">Bank or GCash transfer reference</span><input v-model="references[payout.id]" class="mt-2 w-full rounded-xl border border-court-200 bg-white px-4 py-3" placeholder="Required after sending externally"></label><button :disabled="!references[payout.id]" class="self-end rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40" @click="postAction(payout, 'send', { external_reference: references[payout.id] })">Mark as sent</button></div>
                    <div v-if="payout.can_fail || payout.can_cancel || payout.can_reverse" class="mt-5 grid gap-3 rounded-2xl bg-slate-50 p-4 sm:grid-cols-[1fr_auto]"><label><span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reason</span><input v-model="reasons[payout.id]" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3" placeholder="Required for failed, cancelled, or returned payouts"></label><div class="flex flex-wrap items-end gap-2"><button v-if="payout.can_fail" :disabled="!reasons[payout.id]" class="rounded-xl border border-amber-300 px-3 py-2.5 text-sm font-semibold text-amber-800 disabled:opacity-40" @click="postAction(payout, 'fail', { reason: reasons[payout.id] })">Could not send</button><button v-if="payout.can_cancel" :disabled="!reasons[payout.id]" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-semibold disabled:opacity-40" @click="postAction(payout, 'cancel', { reason: reasons[payout.id] })">Cancel</button><button v-if="payout.can_reverse" :disabled="!reasons[payout.id]" class="rounded-xl border border-red-300 px-3 py-2.5 text-sm font-semibold text-red-700 disabled:opacity-40" @click="postAction(payout, 'reverse', { reason: reasons[payout.id] })">Payment returned</button></div></div>
                </article>
                <div v-if="!payouts.length" class="app-card p-10 text-center text-sm text-slate-500">No owner payouts have been prepared yet.</div>
            </section>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="text-xl font-semibold">Recent earning changes</h2><p class="mt-2 text-sm text-slate-500">A read-only trail of online court prices, refunds, returned payouts, and corrections.</p></div><div class="divide-y divide-slate-100"><div v-for="entry in recentEntries" :key="entry.id" class="flex items-center justify-between gap-4 p-5 sm:px-6"><div><p class="font-semibold">{{ entry.organization }} · {{ entry.type }}</p><p class="mt-1 text-sm text-slate-500">{{ entry.description }}<span v-if="entry.booking_reference"> · {{ entry.booking_reference }}</span></p><p class="mt-1 text-xs text-slate-400">{{ entry.occurred_at }}</p></div><p :class="Number(entry.amount) < 0 ? 'text-red-700' : 'text-court-800'" class="font-semibold">{{ money(entry.amount) }}</p></div><p v-if="!recentEntries.length" class="p-10 text-center text-sm text-slate-500">No online owner earnings have been recorded.</p></div></section>
        </div>
    </PlatformLayout>
</template>
