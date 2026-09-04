<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    schedule: { type: Object, required: true },
    earlyPayout: { type: Object, required: true },
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
const requestForm = useForm({ confirmed: true });
const confirmationOpen = ref(false);

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
    requestForm.post('/owner/earnings/request', {
        preserveScroll: true,
        onSuccess: () => { confirmationOpen.value = false; },
    });
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
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Available balance</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.available) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Completed and cleared earnings ready for payout.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pending earnings</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.pending) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Becomes available after the booking ends and clears.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Processing payout</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.processing) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Reserved safely in a queued or active payout.</p></div>
                <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Paid</p><p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(summary.paid) }}</p><p class="mt-2 text-xs leading-5 text-slate-500">Net transfers recorded as successfully paid.</p></div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="app-card p-5 sm:p-6">
                    <p class="eyebrow">Your next free payout</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ schedule.next_date_label }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        If you do nothing, eligible earnings are automatically queued on the 15th and true last day of each month. Standard scheduled payouts have no owner-facing fee.
                    </p>
                    <div class="mt-5 rounded-2xl bg-court-50 p-4 text-sm text-court-900">
                        <p><strong>Eligible so far:</strong> {{ money(summary.available) }}</p>
                        <p class="mt-1"><strong>Pending:</strong> {{ money(summary.pending) }}</p>
                        <p class="mt-2 text-xs leading-5">Only completed bookings that have passed the {{ schedule.clearing_hours }}-hour clearing period are included.</p>
                    </div>
                    <p v-if="schedule.will_carry_forward" class="mt-3 text-sm font-medium text-amber-800">
                        This balance will carry forward until it reaches the scheduled minimum of {{ money(schedule.minimum_amount) }}.
                    </p>
                </div>

                <div class="app-card p-5 sm:p-6">
                    <p class="eyebrow">Receive available funds sooner</p>
                    <h2 class="mt-2 text-xl font-semibold text-slate-950">Request early payout</h2>
                    <p v-if="earlyPayout.open" class="mt-3 text-sm leading-6 text-slate-600">
                        <strong>{{ earlyPayout.open.reference }}</strong> is {{ earlyPayout.open.status_label.toLowerCase() }} for {{ money(earlyPayout.open.net_amount) }}.
                    </p>
                    <p v-else class="mt-3 text-sm leading-6 text-slate-600">
                        Early payout is optional. Review the transfer fee and amount you receive before confirming, or wait for {{ schedule.next_date_label }} for the free scheduled payout.
                    </p>
                    <div v-if="!earlyPayout.open" class="mt-5 grid grid-cols-3 gap-2 rounded-2xl bg-slate-50 p-4 text-sm">
                        <div><p class="text-xs text-slate-500">Available</p><p class="mt-1 font-semibold">{{ money(earlyPayout.gross_amount) }}</p></div>
                        <div><p class="text-xs text-slate-500">Transfer fee</p><p class="mt-1 font-semibold">{{ money(earlyPayout.fee_amount) }}</p></div>
                        <div><p class="text-xs text-slate-500">You receive</p><p class="mt-1 font-semibold text-court-800">{{ money(earlyPayout.net_amount) }}</p></div>
                    </div>
                    <p v-if="earlyPayout.fee_payer === 'platform' && Number(earlyPayout.fee_amount) > 0" class="mt-2 text-xs text-slate-500">FinACourt pays this transfer fee, so it is not deducted from your amount.</p>
                    <p v-if="earlyPayout.unavailable_reason && !earlyPayout.open" class="mt-3 text-sm font-medium text-amber-800">{{ earlyPayout.unavailable_reason }}</p>
                    <p v-if="requestForm.errors.payout" class="mt-3 text-sm font-medium text-red-700">{{ requestForm.errors.payout }}</p>
                    <button
                        v-if="!earlyPayout.open"
                        type="button"
                        :disabled="!earlyPayout.can_request"
                        class="mt-5 min-h-12 w-full rounded-xl bg-court-700 px-6 py-3 font-semibold text-white hover:bg-court-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                        @click="confirmationOpen = true"
                    >
                        Review early payout
                    </button>
                    <span v-else class="mt-5 inline-flex rounded-full bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900">{{ earlyPayout.open.status_label }}</span>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <p class="eyebrow">How balances move</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">Paid booking → completed booking → clearing → available</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">A customer payment first appears as pending owner earnings. It only becomes available after the reserved court time has ended, the payment remains verified, and the configured clearing period passes.</p>
                    </div>
                    <span class="inline-flex rounded-full bg-court-50 px-4 py-2 text-sm font-semibold text-court-800">No double counting</span>
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
                    <table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Payout</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Gross</th><th class="px-5 py-3 text-right">Fee</th><th class="px-5 py-3 text-right">Net</th><th class="px-5 py-3 text-right">Statement</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><tr v-for="payout in payouts" :key="payout.id"><td class="px-5 py-4"><p class="font-semibold">{{ payout.type_label }}</p><p class="mt-1 text-xs text-slate-500">{{ payout.reference }} · {{ payout.entries_count }} included entries</p><p class="mt-1 text-xs text-slate-400">{{ payout.period }}<span v-if="payout.paid_at"> · Paid {{ payout.paid_at }}</span></p><p v-if="payout.external_reference" class="mt-1 text-xs text-slate-400">Transfer: {{ payout.external_reference }}</p></td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold">{{ payout.status_label }}</span><div v-if="payout.status_reason" class="mt-3 max-w-xs rounded-xl border border-red-200 bg-red-50 p-3 text-xs leading-5 text-red-800"><p class="font-semibold">Reason from FinACourt</p><p class="mt-1">{{ payout.status_reason }}</p></div></td><td class="px-5 py-4 text-right">{{ money(payout.gross_amount) }}</td><td class="px-5 py-4 text-right"><span>{{ money(payout.fee_amount) }}</span><p class="text-xs text-slate-400">{{ payout.fee_payer === 'owner' ? 'Owner-paid' : 'FinACourt-paid' }}</p></td><td class="px-5 py-4 text-right font-semibold">{{ money(payout.net_amount) }}</td><td class="px-5 py-4 text-right"><a :href="`/owner/earnings/payouts/${payout.id}/statement`" class="font-semibold text-court-700 hover:underline">Download details</a></td></tr><tr v-if="!payouts.length"><td colspan="6" class="px-6 py-10 text-center text-slate-500">No payouts have been prepared yet.</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 p-5 sm:px-6"><h2 class="text-xl font-semibold">How your earnings were calculated</h2><p class="mt-2 text-sm text-slate-500">Positive amounts are court prices collected online. Refunds and corrections appear as separate negative amounts.</p></div>
                <div class="divide-y divide-slate-100"><article v-for="entry in entries" :key="entry.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="font-semibold">{{ entry.type_label }}</p><p class="mt-1 text-sm text-slate-600">{{ entry.venue || entry.description }}<span v-if="entry.booking_reference"> · {{ entry.booking_reference }}</span></p><p class="mt-1 text-xs text-slate-400">{{ entry.occurred_at }} · {{ entry.state_label }}</p></div><p :class="Number(entry.amount) < 0 ? 'text-red-700' : 'text-court-800'" class="text-lg font-semibold">{{ money(entry.amount) }}</p></article><p v-if="!entries.length" class="p-10 text-center text-sm text-slate-500">Online court earnings will appear here after a verified online payment.</p></div>
            </section>
        </div>
        <div v-if="confirmationOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" role="dialog" aria-modal="true" aria-labelledby="early-payout-title" @click.self="confirmationOpen = false">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                <p class="eyebrow">Optional early payout</p>
                <h2 id="early-payout-title" class="mt-2 text-2xl font-semibold text-slate-950">Review before requesting</h2>
                <dl class="mt-6 space-y-3 rounded-2xl bg-slate-50 p-5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Available owner earnings</dt><dd class="font-semibold">{{ money(earlyPayout.gross_amount) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-600">Transfer fee</dt><dd class="font-semibold">{{ money(earlyPayout.fee_amount) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base"><dt class="font-semibold">You receive</dt><dd class="font-semibold text-court-800">{{ money(earlyPayout.net_amount) }}</dd></div>
                </dl>
                <div class="mt-5 rounded-2xl border border-court-200 bg-court-50 p-4 text-sm leading-6 text-court-900">
                    You can wait for your free scheduled payout on <strong>{{ schedule.next_date_label }}</strong> to avoid an owner-paid transfer fee.
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <button type="button" class="min-h-12 rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-800" @click="confirmationOpen = false">Wait for free payout</button>
                    <button type="button" :disabled="requestForm.processing" class="min-h-12 rounded-xl bg-court-700 px-5 py-3 font-semibold text-white disabled:opacity-60" @click="requestPayout">{{ requestForm.processing ? 'Requesting…' : `Request ${money(earlyPayout.net_amount)} now` }}</button>
                </div>
            </div>
        </div>
    </OwnerLayout>
</template>
