<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    venues: Array,
    resources: Array,
    timezone: String,
    formValues: Object,
    impact: Object,
});

const form = useForm({
    scope: props.formValues.scope,
    resource_id: props.formValues.resource_id || '',
    resource_ids: props.formValues.resource_ids || [],
    venue_id: props.formValues.venue_id || '',
    starts_at: props.formValues.starts_at,
    until_reopened: Boolean(props.formValues.until_reopened),
    ends_at: props.formValues.ends_at || '',
    reason: props.formValues.reason || '',
    confirmed: false,
    confirmation_token: '',
});
const reviewed = ref(Boolean(props.impact));
const watching = ref(false);
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const scopeOptions = [
    { value: 'court', label: 'One court' },
    { value: 'selected_courts', label: 'Selected courts' },
    { value: 'venue', label: 'Every court at a venue' },
];
const venueOptions = computed(() => props.venues.map((venue) => ({ value: venue.id, label: venue.name })));
const resourceOptions = computed(() => props.resources.map((resource) => ({ value: resource.id, label: `${resource.venue} — ${resource.name} (${resource.sport})` })));

watch(
    () => [form.scope, form.resource_id, [...form.resource_ids], form.venue_id, form.starts_at, form.until_reopened, form.ends_at, form.reason],
    () => {
        if (watching.value) reviewed.value = false;
    },
    { deep: true },
);
watch(() => props.impact, (impact) => {
    reviewed.value = Boolean(impact);
});
watching.value = true;

function toggleResource(id) {
    form.resource_ids = form.resource_ids.includes(id)
        ? form.resource_ids.filter((value) => value !== id)
        : [...form.resource_ids, id];
}

function preview() {
    form.confirmed = false;
    form.confirmation_token = '';
    form.post('/owner/court-closures/preview', { preserveScroll: true });
}

function confirmClosure() {
    if (!reviewed.value || !props.impact) return;
    if (!window.confirm(`Cancel ${props.impact.booking_count} affected booking(s), notify players, and close the selected court time?`)) return;
    form.confirmed = true;
    form.confirmation_token = props.impact.confirmation_token;
    form.post('/owner/court-closures');
}
</script>

<template>
    <Head title="Emergency court closure" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <Link href="/owner/court-closures" class="text-sm font-semibold text-court-700">← Emergency closures</Link>
            <div class="mt-4">
                <p class="eyebrow">Safety and operations</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Close courts and protect affected players</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Use this only when a court cannot operate. FinACourt blocks new reservations immediately, cancels overlapping bookings, notifies players, and sends paid online refunds to the platform for one batch approval.</p>
            </div>

            <form v-if="resources.length" class="mt-8 grid gap-6 xl:grid-cols-[1fr_23rem]" @submit.prevent="preview">
                <div class="space-y-6">
                    <section class="app-card p-6 sm:p-7">
                        <h2 class="text-lg font-semibold">Closure scope</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <label class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">What should close?</span><AppSelect v-model="form.scope" :options="scopeOptions" class="mt-2" /><FormError :message="form.errors.scope" /></label>
                            <label v-if="form.scope === 'court'" class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Court</span><AppSelect v-model="form.resource_id" :options="resourceOptions" class="mt-2" /><FormError :message="form.errors.resource_id" /></label>
                            <label v-if="form.scope === 'venue'" class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Venue</span><AppSelect v-model="form.venue_id" :options="venueOptions" class="mt-2" /><FormError :message="form.errors.venue_id" /></label>
                            <fieldset v-if="form.scope === 'selected_courts'" class="sm:col-span-2">
                                <legend class="text-sm font-medium text-slate-700">Courts</legend>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <label v-for="resource in resources" :key="resource.id" class="flex items-start gap-3 rounded-xl border border-slate-200 p-3">
                                        <input type="checkbox" :checked="form.resource_ids.includes(resource.id)" class="mt-1 size-4 rounded border-slate-300 text-court-700" @change="toggleResource(resource.id)">
                                        <span><strong class="block text-sm">{{ resource.name }}</strong><span class="text-xs text-slate-500">{{ resource.venue }} · {{ resource.sport }}</span></span>
                                    </label>
                                </div>
                                <FormError :message="form.errors.resource_ids" />
                            </fieldset>
                        </div>
                    </section>

                    <section class="app-card p-6 sm:p-7">
                        <h2 class="text-lg font-semibold">When and why</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <label><span class="text-sm font-medium text-slate-700">Closure starts</span><input v-model="form.starts_at" type="datetime-local" class="mt-2 w-full rounded-xl border-slate-300"><span class="mt-1 block text-xs text-slate-400">{{ timezone }}</span><FormError :message="form.errors.starts_at" /></label>
                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 sm:self-start sm:mt-7"><input v-model="form.until_reopened" type="checkbox" class="size-4 rounded border-slate-300 text-court-700"><span><strong class="block text-sm">Until manually reopened</strong><span class="text-xs text-slate-500">Best when repair timing is unknown.</span></span></label>
                            <label v-if="!form.until_reopened"><span class="text-sm font-medium text-slate-700">Expected reopening</span><input v-model="form.ends_at" type="datetime-local" class="mt-2 w-full rounded-xl border-slate-300"><FormError :message="form.errors.ends_at" /></label>
                            <label class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Reason shown to affected players</span><textarea v-model="form.reason" rows="4" maxlength="500" placeholder="Example: The court flooded after heavy rain and is unsafe to use." class="mt-2 w-full rounded-xl border-slate-300" /><FormError :message="form.errors.reason" /></label>
                        </div>
                    </section>

                    <section v-if="impact" class="app-card overflow-hidden">
                        <div class="border-b border-slate-100 p-6"><p class="eyebrow">Required preview</p><h2 class="mt-1 text-xl font-semibold">Confirm the impact</h2><p v-if="!reviewed" class="mt-2 text-sm font-medium text-amber-700">The form changed. Preview again before confirming.</p></div>
                        <div class="grid gap-3 border-b border-slate-100 p-6 sm:grid-cols-4"><div><p class="text-xs text-slate-500">Courts closed</p><strong class="mt-1 block text-2xl">{{ impact.court_count }}</strong></div><div><p class="text-xs text-slate-500">Bookings cancelled</p><strong class="mt-1 block text-2xl">{{ impact.booking_count }}</strong></div><div><p class="text-xs text-slate-500">Online refunds</p><strong class="mt-1 block text-2xl">{{ impact.online_refund_count }}</strong></div><div><p class="text-xs text-slate-500">Refund total</p><strong class="mt-1 block text-2xl">{{ money.format(Number(impact.online_refund_total)) }}</strong></div></div>
                        <div v-if="impact.bookings.length" class="divide-y divide-slate-100"><article v-for="booking in impact.bookings" :key="booking.id" class="grid gap-2 px-6 py-4 sm:grid-cols-[1fr_auto]"><div><strong>{{ booking.customer_name }} · {{ booking.reference }}</strong><p class="mt-1 text-sm text-slate-500">{{ booking.venue }} · {{ booking.resource }} · {{ booking.date }}, {{ booking.time }}</p></div><div class="text-sm font-semibold text-slate-700">{{ booking.payment }}<span v-if="booking.amount" class="block text-right text-xs font-normal text-slate-400">{{ money.format(Number(booking.amount)) }}</span></div></article></div>
                        <div v-else class="p-6 text-sm text-slate-500">No active bookings overlap this closure. New reservations will still be blocked.</div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
                    <section class="rounded-2xl bg-court-950 p-5 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-court-300">Order of operations</p><ol class="mt-4 space-y-3 text-sm leading-6 text-court-50"><li>1. Courts are blocked first.</li><li>2. Overlapping bookings are cancelled.</li><li>3. Players and platform admins are notified.</li><li>4. Online refunds wait for platform approval, then submit automatically.</li></ol></section>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl border border-court-700 bg-white px-4 py-3 text-sm font-semibold text-court-800 disabled:opacity-50">{{ form.processing ? 'Checking…' : 'Preview affected bookings' }}</button>
                    <button v-if="impact" type="button" :disabled="form.processing || !reviewed" class="w-full rounded-xl bg-red-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40" @click="confirmClosure">Confirm emergency closure</button>
                    <FormError :message="form.errors.confirmed" />
                </aside>
            </form>
            <div v-else class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">Add an active court before creating an emergency closure.</div>
        </div>
    </OwnerLayout>
</template>
