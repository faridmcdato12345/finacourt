<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object, resource: Object, weekdays: Array, rules: Array });
const editingId = ref(null);
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: props.resource.currency || 'PHP' });
const form = useForm({
    name: '',
    days_of_week: [1, 2, 3, 4, 5],
    starts_at_time: '17:00',
    ends_at_time: '22:00',
    hourly_rate: '',
});
const title = computed(() => editingId.value ? 'Edit time-based price' : 'Add a time-based price');

function resetForm() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function editRule(rule) {
    editingId.value = rule.id;
    form.name = rule.name;
    form.days_of_week = [...rule.days_of_week];
    form.starts_at_time = rule.starts_at_time;
    form.ends_at_time = rule.ends_at_time;
    form.hourly_rate = rule.hourly_rate;
    form.clearErrors();
    document.querySelector('#pricing-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function submit() {
    const base = `/owner/venues/${props.venue.id}/resources/${props.resource.id}/pricing`;
    const options = { preserveScroll: true, onSuccess: resetForm };

    if (editingId.value) {
        form.put(`${base}/${editingId.value}`, options);
    } else {
        form.post(base, options);
    }
}

function destroyRule(rule) {
    if (window.confirm(`Remove “${rule.name}”? The regular hourly price will apply instead.`)) {
        router.delete(`/owner/venues/${props.venue.id}/resources/${props.resource.id}/pricing/${rule.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="`${resource.name} time-based pricing`" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <Link :href="`/owner/venues/${venue.id}`" class="text-sm font-semibold text-court-700">← Back to {{ venue.name }}</Link>

            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Court pricing</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Time-based prices for {{ resource.name }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Charge a different normal price on selected days and hours. Times without a rule use the regular hourly price.</p>
                </div>
                <div class="shrink-0 rounded-2xl bg-court-950 px-5 py-4 text-white">
                    <p class="text-xs uppercase tracking-wider text-court-200">Regular fallback</p>
                    <p class="mt-1 text-2xl font-semibold">{{ money.format(resource.base_hourly_rate) }}<span class="text-sm font-normal text-court-100/70"> / hour</span></p>
                </div>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_23rem]">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div><h2 class="text-lg font-semibold text-slate-950">Your pricing schedule</h2><p class="mt-1 text-sm text-slate-500">Rules cannot overlap on the same court and day.</p></div>
                        <span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">{{ rules.length }} {{ rules.length === 1 ? 'rule' : 'rules' }}</span>
                    </div>

                    <div v-if="rules.length" class="mt-6 space-y-3">
                        <article v-for="rule in rules" :key="rule.id" class="rounded-2xl border border-slate-200 p-5">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950">{{ rule.name }}</h3><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ money.format(rule.hourly_rate) }}/hour</span></div>
                                    <p class="mt-2 text-sm text-slate-600">{{ rule.days_label }}</p>
                                    <p class="mt-1 text-sm font-medium text-slate-800">{{ rule.starts_at_time }}–{{ rule.ends_at_time }}</p>
                                </div>
                                <div class="flex gap-2"><button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700" @click="editRule(rule)">Edit</button><button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" @click="destroyRule(rule)">Remove</button></div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center"><p class="font-semibold text-slate-800">No time-based prices yet</p><p class="mt-2 text-sm text-slate-500">All schedules currently use {{ money.format(resource.base_hourly_rate) }} per hour.</p></div>

                    <div class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600"><strong class="text-slate-800">Existing bookings are protected.</strong> Editing or removing a pricing rule only affects new bookings. Prices already saved on reservations do not change.</div>
                </section>

                <form id="pricing-form" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                    <h2 class="text-lg font-semibold text-slate-950">{{ title }}</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Example: “Weekday peak hours” from 5:00 PM to 10:00 PM.</p>
                    <FormError :message="form.errors.schedule" class="mt-4" />

                    <label class="mt-5 block"><span class="text-sm font-medium text-slate-800">Price name</span><input v-model="form.name" required maxlength="80" placeholder="Evening peak hours" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.name" /></label>

                    <fieldset class="mt-5"><legend class="text-sm font-medium text-slate-800">Applies every</legend><div class="mt-3 grid grid-cols-2 gap-2"><label v-for="day in weekdays" :key="day.value" :class="['flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm', form.days_of_week.includes(day.value) ? 'border-court-400 bg-court-50 text-court-900' : 'border-slate-200 text-slate-600']"><input v-model="form.days_of_week" type="checkbox" :value="day.value" class="rounded border-slate-300 text-court-700">{{ day.label }}</label></div><FormError :message="form.errors.days_of_week" /></fieldset>

                    <div class="mt-5 grid grid-cols-2 gap-3"><label><span class="text-sm font-medium text-slate-800">Starts</span><input v-model="form.starts_at_time" required type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3"><FormError :message="form.errors.starts_at_time" /></label><label><span class="text-sm font-medium text-slate-800">Ends</span><input v-model="form.ends_at_time" required type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3"><FormError :message="form.errors.ends_at_time" /></label></div>

                    <label class="mt-5 block"><span class="text-sm font-medium text-slate-800">Hourly price</span><div class="mt-2 flex rounded-xl border border-slate-300"><span class="border-r border-slate-200 px-4 py-3 text-slate-500">₱</span><input v-model="form.hourly_rate" required type="number" min="0" max="999999.99" step="0.01" class="min-w-0 flex-1 rounded-r-xl border-0 px-4 py-3"></div><FormError :message="form.errors.hourly_rate" /></label>

                    <button type="submit" :disabled="form.processing" class="mt-6 w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Saving…' : editingId ? 'Save changes' : 'Add price' }}</button>
                    <button v-if="editingId" type="button" class="mt-2 w-full rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50" @click="resetForm">Cancel editing</button>
                </form>
            </div>
        </div>
    </OwnerLayout>
</template>
