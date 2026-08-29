<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    payoutRequest: { type: Object, required: true },
    profile: { type: Object, default: null },
    methods: { type: Array, required: true },
    payouts: { type: Array, required: true },
    entries: { type: Array, required: true },
});

const form = useForm({
    method: props.profile?.method || 'gcash',
    account_name: props.profile?.account_name || '',
    bank_name: '',
    account_number: '',
    mobile_number: '',
    instructions: '',
    is_active: true,
});
const requestForm = useForm({});

const money = (value) => new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
}).format(Number(value || 0));

function saveDetails() {
    form.put('/owner/earnings/payment-details', {
        preserveScroll: true,
        onSuccess: () => form.reset('bank_name', 'account_number', 'mobile_number', 'instructions'),
    });
}

function requestPayout() {
    requestForm.post('/owner/earnings/request', { preserveScroll: true });
}
</script>

<template>
    <Head title="Court earnings" />
    <OwnerLayout>
        <div class="space-y-7">
            <section>
                <p class="eyebrow">Money from online bookings</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Your court earnings</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                    See the court price collected by FinACourt and where each payout stands. Payments collected directly at your venue do not appear here because you already received that money.
                </p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ready to be paid</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.ready) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Can be included in the next payout.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Still waiting</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.waiting) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Inside the short safety period for refunds or payment checks.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Being prepared</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.being_prepared) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">FinACourt is reviewing or preparing the transfer.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Already sent</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.sent) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Recorded payouts successfully sent to you.</p></div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="eyebrow">Ask FinACourt to send your money</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">Request a payout</h2>
                        <p v-if="payoutRequest.open" class="mt-2 text-sm leading-6 text-slate-600">
                            <strong>{{ payoutRequest.open.reference }}</strong> for {{ money(payoutRequest.open.amount) }} is {{ payoutRequest.open.status_label.toLowerCase() }}.
                            <span v-if="payoutRequest.open.was_requested_by_owner"> You already sent this request to FinACourt.</span>
                        </p>
                        <p v-else class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            When your ready balance reaches {{ money(payoutRequest.minimum_amount) }}, you can ask us to review and send it to your saved bank or GCash account. The amount is calculated from verified online payments and cannot be changed here.
                        </p>
                        <p v-if="payoutRequest.unavailable_reason && !payoutRequest.open" class="mt-3 text-sm font-medium text-amber-800">
                            {{ payoutRequest.unavailable_reason }}
                        </p>
                        <p v-if="requestForm.errors.payout" class="mt-3 text-sm font-medium text-red-700">{{ requestForm.errors.payout }}</p>
                    </div>
                    <button
                        v-if="!payoutRequest.open"
                        type="button"
                        :disabled="!payoutRequest.can_request || requestForm.processing"
                        class="min-h-12 rounded-xl bg-court-700 px-6 py-3 font-semibold text-white hover:bg-court-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        @click="requestPayout"
                    >
                        {{ requestForm.processing ? 'Sending request…' : `Request ${money(summary.ready)} payout` }}
                    </button>
                    <span v-else class="inline-flex rounded-full bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900">{{ payoutRequest.open.status_label }}</span>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
                <div class="app-card p-5 sm:p-6">
                    <p class="eyebrow">Saved payment details</p>
                    <h2 class="mt-1 text-xl font-semibold">Where we should send your money</h2>
                    <div v-if="profile" class="mt-5 rounded-2xl bg-court-950 p-5 text-white">
                        <p class="text-sm text-court-100/70">{{ profile.method_label }}</p>
                        <p class="mt-2 text-lg font-semibold">{{ profile.account_name }}</p>
                        <p class="mt-1 text-sm text-court-100/80">{{ profile.summary }}</p>
                        <p class="mt-4 text-xs text-court-100/60">{{ profile.is_active ? 'Ready for future payouts' : 'Payouts are paused' }}</p>
                    </div>
                    <div v-else class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
                        Add your bank or GCash details before FinACourt can prepare a payout.
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-500">For safety, account numbers are encrypted and are not shown again in full. Enter the complete details below whenever you need to replace them.</p>
                </div>

                <form class="app-card p-5 sm:p-6" @submit.prevent="saveDetails">
                    <h2 class="text-xl font-semibold">Add or replace payment details</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Use an account that belongs to the venue owner or authorized business.</p>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-medium">How should we send it?</span>
                            <AppSelect v-model="form.method" :options="methods" class="mt-2" />
                            <span v-if="form.errors.method" class="mt-1 block text-xs text-red-600">{{ form.errors.method }}</span>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium">Name on the account</span>
                            <input v-model="form.account_name" required autocomplete="name" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-court-600 focus:ring-4 focus:ring-court-100">
                            <span v-if="form.errors.account_name" class="mt-1 block text-xs text-red-600">{{ form.errors.account_name }}</span>
                        </label>
                        <label v-if="form.method === 'bank_transfer'" class="block">
                            <span class="text-sm font-medium">Bank name</span>
                            <input v-model="form.bank_name" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-court-600 focus:ring-4 focus:ring-court-100">
                            <span v-if="form.errors.bank_name" class="mt-1 block text-xs text-red-600">{{ form.errors.bank_name }}</span>
                        </label>
                        <label v-if="form.method === 'bank_transfer'" class="block">
                            <span class="text-sm font-medium">Account number</span>
                            <input v-model="form.account_number" required inputmode="numeric" autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-court-600 focus:ring-4 focus:ring-court-100">
                            <span v-if="form.errors.account_number" class="mt-1 block text-xs text-red-600">{{ form.errors.account_number }}</span>
                        </label>
                        <label v-if="form.method === 'gcash'" class="block sm:col-span-2">
                            <span class="text-sm font-medium">GCash mobile number</span>
                            <input v-model="form.mobile_number" required inputmode="tel" autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-court-600 focus:ring-4 focus:ring-court-100">
                            <span v-if="form.errors.mobile_number" class="mt-1 block text-xs text-red-600">{{ form.errors.mobile_number }}</span>
                        </label>
                        <label v-if="form.method === 'other'" class="block sm:col-span-2">
                            <span class="text-sm font-medium">How should we send it?</span>
                            <textarea v-model="form.instructions" required rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-court-600 focus:ring-4 focus:ring-court-100" />
                            <span v-if="form.errors.instructions" class="mt-1 block text-xs text-red-600">{{ form.errors.instructions }}</span>
                        </label>
                    </div>
                    <label class="mt-5 flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><input v-model="form.is_active" type="checkbox" class="mt-1 rounded border-slate-300 text-court-700 focus:ring-court-600"><span><strong class="text-slate-900">Allow payouts to these details.</strong><br>Turn this off if the account should not receive money yet.</span></label>
                    <button :disabled="form.processing" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 font-semibold text-white hover:bg-court-800 disabled:opacity-60">{{ form.processing ? 'Saving…' : 'Save payment details' }}</button>
                </form>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="text-xl font-semibold">Payout history</h2><p class="mt-2 text-sm text-slate-500">Download a statement to see which online bookings and refunds were included.</p></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Payout</th><th class="px-5 py-3">Dates covered</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Amount</th><th class="px-5 py-3 text-right">Statement</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="payout in payouts" :key="payout.id"><td class="px-5 py-4"><p class="font-semibold">{{ payout.reference }}</p><p v-if="payout.external_reference" class="mt-1 text-xs text-slate-400">Transfer: {{ payout.external_reference }}</p></td><td class="px-5 py-4 text-slate-600">{{ payout.period }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ payout.status_label }}</span></td><td class="px-5 py-4 text-right font-semibold">{{ money(payout.amount) }}</td><td class="px-5 py-4 text-right"><a :href="`/owner/earnings/payouts/${payout.id}/statement`" class="font-semibold text-court-700 hover:underline">Download CSV</a></td></tr><tr v-if="!payouts.length"><td colspan="5" class="px-6 py-10 text-center text-slate-500">No payouts have been prepared yet.</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="text-xl font-semibold">How your earnings were calculated</h2><p class="mt-2 text-sm text-slate-500">Positive amounts are court prices collected online. Refunds and corrections appear as separate negative amounts.</p></div>
                <div class="divide-y divide-slate-100"><article v-for="entry in entries" :key="entry.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-semibold">{{ entry.type_label }}</p><p class="mt-1 text-sm text-slate-600">{{ entry.venue || entry.description }}<span v-if="entry.booking_reference"> · {{ entry.booking_reference }}</span></p><p class="mt-1 text-xs text-slate-400">{{ entry.occurred_at }} · {{ entry.state_label }}</p></div><p :class="Number(entry.amount) < 0 ? 'text-red-700' : 'text-court-800'" class="text-lg font-semibold">{{ money(entry.amount) }}</p></article><p v-if="!entries.length" class="p-10 text-center text-sm text-slate-500">Online court earnings will appear here after a verified online payment.</p></div>
            </section>
        </div>
    </OwnerLayout>
</template>
