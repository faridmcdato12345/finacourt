<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({
    activeRule: { type: Object, default: null },
    rules: { type: Array, default: () => [] },
    metrics: { type: Object, required: true },
    provider: { type: Object, required: true },
    feeTypes: { type: Array, required: true },
});

const form = useForm({
    name: 'FinACourt booking service fee',
    fee_type: 'percentage',
    percentage_rate: '5.00',
    fixed_amount: '',
    minimum_fee_amount: '0.00',
    maximum_fee_amount: '',
    currency: 'PHP',
    is_active: true,
    starts_at: '',
    ends_at: '',
});

const money = (value) => new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
}).format(Number(value || 0));
const number = (value) => new Intl.NumberFormat('en-PH').format(Number(value || 0));

function saveRule() {
    form.post('/platform/payments/service-fees', {
        preserveScroll: true,
        onSuccess: () => form.reset('fixed_amount', 'maximum_fee_amount', 'starts_at', 'ends_at'),
    });
}

function toggleRule(rule) {
    router.patch(`/platform/payments/service-fees/${rule.id}`, {
        is_active: !rule.is_active,
    }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Payment settings" />
    <PlatformLayout>
        <div class="space-y-8">
            <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="eyebrow">Platform revenue</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">
                        Booking fee settings
                    </h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        Set the small FinACourt fee players pay on new marketplace bookings. Court prices stay separate, so owners still see their own venue revenue clearly.
                    </p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Active checkout mode</p>
                    <p class="mt-1 font-semibold text-slate-950">{{ provider.mode_label }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ provider.hosted_checkout_available ? 'Online checkout is ready.' : 'Online checkout is not fully configured.' }}
                    </p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="metric-card">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Fee collected/qualified</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(metrics.service_fee_total) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">From confirmed marketplace bookings only.</p>
                </div>
                <div class="metric-card">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pending fee</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(metrics.pending_service_fee_total) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Held or confirmed bookings still waiting for payment.</p>
                </div>
                <div class="metric-card">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Bookings with fee</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">{{ number(metrics.bookings_with_fee) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">{{ number(metrics.qualified_bookings) }} qualified marketplace bookings.</p>
                </div>
                <div class="metric-card">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Average fee</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-950">{{ money(metrics.average_service_fee) }}</p>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Average across bookings where a fee was applied.</p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <div class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                        <p class="eyebrow">Current rule</p>
                        <h2 class="mt-1 text-xl font-semibold">What players pay now</h2>
                    </div>
                    <div class="p-5 sm:p-6">
                        <div v-if="activeRule" class="rounded-2xl bg-court-950 p-5 text-white">
                            <p class="text-sm text-court-100/70">{{ activeRule.name }}</p>
                            <p class="mt-3 text-3xl font-semibold">{{ activeRule.summary }}</p>
                            <p class="mt-3 text-sm leading-6 text-court-100/70">
                                Optional cap:
                                <span v-if="activeRule.maximum_fee_amount">{{ money(activeRule.maximum_fee_amount) }}</span>
                                <span v-else>none</span>.
                                Minimum fee: {{ money(activeRule.minimum_fee_amount) }}.
                            </p>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5">
                            <h3 class="font-semibold text-slate-900">No booking fee is active</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">
                                Players currently pay only the court price. Save an active rule to start adding a FinACourt service fee to new marketplace bookings.
                            </p>
                        </div>

                        <div class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                            Existing bookings do not change when this rule changes. Each new booking keeps the exact fee that was shown and calculated by the server.
                        </div>
                    </div>
                </div>

                <form class="app-card p-5 sm:p-6" @submit.prevent="saveRule">
                    <div>
                        <p class="eyebrow">New fee rule</p>
                        <h2 class="mt-1 text-xl font-semibold">Set a booking fee</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            If you turn this on, older active rules are paused and this rule is used for new player bookings.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="block sm:col-span-2">
                            <span class="text-sm font-medium">Fee name</span>
                            <input v-model="form.name" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100" />
                            <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Fee type</span>
                            <AppSelect v-model="form.fee_type" :options="feeTypes" class="mt-2 rounded-xl border border-slate-300 px-4 py-3 shadow-sm" />
                            <span v-if="form.errors.fee_type" class="mt-1 block text-xs text-red-600">{{ form.errors.fee_type }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Currency</span>
                            <input v-model="form.currency" maxlength="3" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 uppercase shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100" />
                            <span v-if="form.errors.currency" class="mt-1 block text-xs text-red-600">{{ form.errors.currency }}</span>
                        </label>

                        <label v-if="form.fee_type === 'percentage'" class="block">
                            <span class="text-sm font-medium">Percentage</span>
                            <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-court-600 focus-within:ring-4 focus-within:ring-court-100">
                                <input v-model="form.percentage_rate" type="number" min="0.01" max="100" step="0.01" inputmode="decimal" required class="w-full border-0 px-4 py-3 focus:ring-0" />
                                <span class="grid w-12 place-items-center border-l border-slate-200 text-slate-500">%</span>
                            </div>
                            <span v-if="form.errors.percentage_rate" class="mt-1 block text-xs text-red-600">{{ form.errors.percentage_rate }}</span>
                        </label>

                        <label v-else class="block">
                            <span class="text-sm font-medium">Fixed amount</span>
                            <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-court-600 focus-within:ring-4 focus-within:ring-court-100">
                                <span class="grid w-12 place-items-center border-r border-slate-200 text-slate-500">₱</span>
                                <input v-model="form.fixed_amount" type="number" min="0.01" step="0.01" inputmode="decimal" required class="w-full border-0 px-4 py-3 focus:ring-0" />
                            </div>
                            <span v-if="form.errors.fixed_amount" class="mt-1 block text-xs text-red-600">{{ form.errors.fixed_amount }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Minimum fee</span>
                            <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-court-600 focus-within:ring-4 focus-within:ring-court-100">
                                <span class="grid w-12 place-items-center border-r border-slate-200 text-slate-500">₱</span>
                                <input v-model="form.minimum_fee_amount" type="number" min="0" step="0.01" inputmode="decimal" class="w-full border-0 px-4 py-3 focus:ring-0" />
                            </div>
                            <span v-if="form.errors.minimum_fee_amount" class="mt-1 block text-xs text-red-600">{{ form.errors.minimum_fee_amount }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Maximum fee <span class="font-normal text-slate-400">optional</span></span>
                            <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-court-600 focus-within:ring-4 focus-within:ring-court-100">
                                <span class="grid w-12 place-items-center border-r border-slate-200 text-slate-500">₱</span>
                                <input v-model="form.maximum_fee_amount" type="number" min="0" step="0.01" inputmode="decimal" placeholder="No cap" class="w-full border-0 px-4 py-3 focus:ring-0" />
                            </div>
                            <span v-if="form.errors.maximum_fee_amount" class="mt-1 block text-xs text-red-600">{{ form.errors.maximum_fee_amount }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Starts at <span class="font-normal text-slate-400">optional</span></span>
                            <input v-model="form.starts_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100" />
                            <span v-if="form.errors.starts_at" class="mt-1 block text-xs text-red-600">{{ form.errors.starts_at }}</span>
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">Ends at <span class="font-normal text-slate-400">optional</span></span>
                            <input v-model="form.ends_at" type="datetime-local" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100" />
                            <span v-if="form.errors.ends_at" class="mt-1 block text-xs text-red-600">{{ form.errors.ends_at }}</span>
                        </label>
                    </div>

                    <label class="mt-5 flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                        <input v-model="form.is_active" type="checkbox" class="mt-1 rounded border-slate-300 text-court-700 focus:ring-court-600" />
                        <span><strong class="text-slate-900">Turn this fee on after saving.</strong><br>When active, it applies to new player bookings and is shown before checkout.</span>
                    </label>

                    <button :disabled="form.processing" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">
                        {{ form.processing ? 'Saving…' : 'Save booking fee rule' }}
                    </button>
                </form>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <h2 class="text-xl font-semibold">Fee rules history</h2>
                    <p class="mt-2 text-sm text-slate-500">Rules are kept for audit. Pausing a rule does not change old booking snapshots.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3 sm:px-6">Rule</th>
                                <th class="px-5 py-3">Fee</th>
                                <th class="px-5 py-3">Min / Max</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 sm:px-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="rule in rules" :key="rule.id">
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-semibold text-slate-950">{{ rule.name }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Created {{ rule.created_at || '—' }} <span v-if="rule.created_by">by {{ rule.created_by }}</span></p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-900">{{ rule.summary }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ rule.fee_type_label }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600">
                                    {{ money(rule.minimum_fee_amount) }} /
                                    <span v-if="rule.maximum_fee_amount">{{ money(rule.maximum_fee_amount) }}</span>
                                    <span v-else>No cap</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span :class="rule.is_active ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-xs font-semibold">
                                        {{ rule.is_active ? 'Active' : 'Paused' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <button class="rounded-lg px-3 py-2 text-xs font-semibold text-court-700 hover:bg-court-50" @click="toggleRule(rule)">
                                        {{ rule.is_active ? 'Pause' : 'Make active' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!rules.length">
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500">No booking fee rules have been created yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
                    <h2 class="text-xl font-semibold">Recent fee-bearing payments</h2>
                    <p class="mt-2 text-sm text-slate-500">Useful for checking that player total, court price, and FinACourt fee are separated.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3 sm:px-6">Payment</th>
                                <th class="px-5 py-3">Venue</th>
                                <th class="px-5 py-3 text-right">Court price</th>
                                <th class="px-5 py-3 text-right">FinACourt fee</th>
                                <th class="px-5 py-3 text-right sm:px-6">Player total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="payment in metrics.recent_payments" :key="payment.id">
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="font-semibold text-slate-950">{{ payment.reference }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ payment.status }} · {{ payment.created_at }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ payment.venue || 'Venue unavailable' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ payment.booking_reference }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">{{ money(payment.venue_amount) }}</td>
                                <td class="px-5 py-4 text-right">{{ money(payment.platform_service_fee_amount) }}</td>
                                <td class="px-5 py-4 text-right font-semibold sm:px-6">{{ money(payment.amount) }}</td>
                            </tr>
                            <tr v-if="!metrics.recent_payments.length">
                                <td colspan="5" class="px-6 py-10 text-center text-slate-500">No service-fee payments yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </PlatformLayout>
</template>
