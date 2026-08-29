<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from './AppSelect.vue';
import FormError from './FormError.vue';

const props = defineProps({
    promotion: { type: Object, default: null },
    venues: Array,
    types: Array,
    discountTypes: Array,
    weekdays: Array,
    goals: Array,
    statuses: Array,
    opportunities: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
});

let slotKey = 0;
const initialSlots = props.promotion?.slots || props.defaults.slots || [];
const form = useForm({
    venue_id: props.promotion?.venue_id || props.defaults.venue_id || props.venues[0]?.id || '',
    resource_id: props.promotion?.resource_id || props.defaults.resource_id || '',
    audience_sport_id: props.promotion?.audience_sport_id || props.defaults.audience_sport_id || '',
    title: props.promotion?.title || '',
    description: props.promotion?.description || '',
    goal: props.promotion?.goal || props.defaults.goal || 'fill_empty_slots',
    status: props.promotion?.status || props.defaults.status || 'draft',
    promotion_type: props.promotion?.promotion_type || props.defaults.promotion_type || 'deal',
    discount_type: props.promotion?.discount_type || 'percentage',
    discount_value: props.promotion?.discount_value || '',
    starts_on: props.promotion?.starts_on || props.defaults.starts_on || '',
    ends_on: props.promotion?.ends_on || props.defaults.ends_on || '',
    days_of_week: props.promotion?.days_of_week || [],
    starts_at_time: props.promotion?.starts_at_time || '',
    ends_at_time: props.promotion?.ends_at_time || '',
    slots: initialSlots.map((slot) => ({ ...slot, _key: ++slotKey })),
    is_public: props.promotion?.is_public ?? false,
});

const resources = computed(() => props.venues.find((venue) => String(venue.id) === String(form.venue_id))?.resources || []);
const sports = computed(() => {
    const seen = new Set();

    return resources.value.reduce((options, resource) => {
        if (!seen.has(resource.sport_id)) {
            seen.add(resource.sport_id);
            options.push({ value: resource.sport_id, label: resource.sport });
        }

        return options;
    }, []);
});
const visibleOpportunities = computed(() => props.opportunities
    .filter((slot) => String(slot.venue_id) === String(form.venue_id))
    .slice(0, 12));

function venueChanged() {
    const eligibleIds = new Set(resources.value.map((resource) => String(resource.id)));

    if (!eligibleIds.has(String(form.resource_id))) form.resource_id = '';
    if (!sports.value.some((sport) => String(sport.value) === String(form.audience_sport_id))) form.audience_sport_id = '';
    form.slots = form.slots.filter((slot) => eligibleIds.has(String(slot.resource_id)));
}

function addSlot(opportunity = null) {
    const resourceId = opportunity?.resource_id || form.resource_id || resources.value.find((resource) => resource.is_active)?.id || '';
    const date = opportunity?.slot_date || form.starts_on;
    form.slots.push({
        id: null,
        resource_id: resourceId,
        slot_date: date,
        starts_at_time: opportunity?.starts_at_time || '18:00',
        ends_at_time: opportunity?.ends_at_time || '19:00',
        _key: ++slotKey,
    });
    form.promotion_type = 'specific_slots';
    form.goal = opportunity?.is_last_minute ? 'promote_today_or_tonight' : 'promote_specific_slots';

    if (date && (!form.starts_on || date < form.starts_on)) form.starts_on = date;
    if (date && (!form.ends_on || date > form.ends_on)) form.ends_on = date;
}

function removeSlot(index) {
    form.slots.splice(index, 1);
}

function slotResources() {
    return resources.value.map((resource) => ({
        value: resource.id,
        label: `${resource.name} · ${resource.sport}${resource.is_active ? '' : ' · not bookable'}`,
        disabled: !resource.is_active,
    }));
}

function submit() {
    if (props.promotion) form.put(`/owner/promotions/${props.promotion.id}`);
    else form.post('/owner/promotions');
}
</script>

<template>
    <form class="space-y-7" @submit.prevent="submit">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Deal goal</p>
            <h2 class="mt-2 text-lg font-semibold">What should this deal help with?</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Choose the main reason for creating this deal. This helps FinACourt present it clearly; you will still choose the venue, court times, price, and whether players can see it in the sections below.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <label v-for="goal in goals" :key="goal.value" :class="['group cursor-pointer rounded-2xl border p-5 transition', form.goal === goal.value ? 'border-court-500 bg-court-50 shadow-sm ring-2 ring-court-100' : 'border-slate-200 bg-white hover:border-court-300 hover:bg-court-50/30']">
                    <input v-model="form.goal" type="radio" :value="goal.value" class="sr-only">
                    <span class="flex items-start gap-3">
                        <span aria-hidden="true" :class="['mt-0.5 grid size-6 shrink-0 place-items-center rounded-full border text-xs font-bold transition', form.goal === goal.value ? 'border-court-700 bg-court-700 text-white' : 'border-slate-300 bg-white text-transparent group-hover:border-court-400']">✓</span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-950">{{ goal.label }}</span>
                            <span class="mt-1.5 block text-xs leading-5 text-slate-500">{{ goal.description }}</span>
                            <span :class="['mt-3 block text-xs font-semibold', form.goal === goal.value ? 'text-court-800' : 'text-slate-400']">{{ form.goal === goal.value ? 'Selected' : 'Choose this goal' }}</span>
                        </span>
                    </span>
                </label>
            </div>
            <FormError :message="form.errors.goal" />
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Deal details</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label class="sm:col-span-2"><span class="text-sm font-medium">Title</span><input v-model="form.title" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.title" /></label>
                <label class="sm:col-span-2"><span class="text-sm font-medium">Description</span><textarea v-model="form.description" rows="4" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></textarea><FormError :message="form.errors.description" /></label>
                <label><span class="text-sm font-medium">Deal type</span><AppSelect v-model="form.promotion_type" :options="types" class="mt-2" aria-label="Deal type" /><FormError :message="form.errors.promotion_type" /></label>
                <label><span class="text-sm font-medium">Deal status</span><AppSelect v-model="form.status" :options="statuses" class="mt-2" aria-label="Deal status" /><FormError :message="form.errors.status" /></label>
                <label><span class="text-sm font-medium">Venue</span><AppSelect v-model="form.venue_id" :options="venues" option-value="id" option-label="name" class="mt-2" aria-label="Venue for this deal" @change="venueChanged" /><FormError :message="form.errors.venue_id" /></label>
                <label><span class="text-sm font-medium">Show to players looking for <span class="font-normal text-slate-400">optional</span></span><AppSelect v-model="form.audience_sport_id" :options="[{ value: '', label: 'Any sport at this venue' }, ...sports]" class="mt-2" aria-label="Sport this deal is for" /><FormError :message="form.errors.audience_sport_id" /></label>
                <label class="sm:col-span-2"><span class="text-sm font-medium">Court <span class="font-normal text-slate-400">optional</span></span><AppSelect v-model="form.resource_id" :options="[{ value: '', label: 'All bookable courts at this venue' }, ...resources.map((resource) => ({ value: resource.id, label: `${resource.name} · ₱${resource.base_hourly_rate}/hour${resource.is_active ? '' : ' · not bookable'}`, disabled: !resource.is_active }))]" class="mt-2" aria-label="Court for this deal" /><FormError :message="form.errors.resource_id" /></label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Price</h2><p class="mt-1 text-sm text-slate-500">Leave both fields empty if you only want to highlight this venue without changing the price. FinACourt calculates the final price when a player books.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label><span class="text-sm font-medium">Discount method</span><AppSelect v-model="form.discount_type" :options="[{ value: '', label: 'No price discount' }, ...discountTypes]" class="mt-2" aria-label="Discount method" /><FormError :message="form.errors.discount_type" /></label>
                <label><span class="text-sm font-medium">Value</span><input v-model="form.discount_value" type="number" min="0" max="999999.99" step="0.01" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" :placeholder="form.discount_type === 'percentage' ? '20' : '500'"><FormError :message="form.errors.discount_value" /></label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold">When this deal runs</h2><p class="mt-1 text-sm text-slate-500">Dates and times use your venue time. Any selected court times must fit inside these dates.</p>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <label><span class="text-sm font-medium">Starts on</span><input v-model="form.starts_on" type="date" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.starts_on" /></label>
                <label><span class="text-sm font-medium">Ends on</span><input v-model="form.ends_on" type="date" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.ends_on" /></label>
                <div class="sm:col-span-2"><span class="text-sm font-medium">Days of the week <span class="font-normal text-slate-400">leave blank for every day</span></span><div class="mt-3 flex flex-wrap gap-2"><label v-for="day in weekdays" :key="day.value" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input v-model="form.days_of_week" type="checkbox" :value="day.value" class="rounded border-slate-300">{{ day.label }}</label></div><FormError :message="form.errors.days_of_week" /></div>
                <label><span class="text-sm font-medium">Daily start time <span class="font-normal text-slate-400">optional</span></span><input v-model="form.starts_at_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.starts_at_time" /></label>
                <label><span class="text-sm font-medium">Daily end time <span class="font-normal text-slate-400">optional</span></span><input v-model="form.ends_at_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.ends_at_time" /></label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Exact court times</p><h2 class="mt-2 text-lg font-semibold">Court times in this deal</h2><p class="mt-1 text-sm text-slate-500">One deal can include more than one exact court, date, and time.</p></div><button type="button" class="rounded-xl border border-court-300 px-4 py-2.5 text-sm font-semibold text-court-800" @click="addSlot()">Add custom time</button></div>
            <FormError :message="form.errors.slots" />

            <div v-if="visibleOpportunities.length" class="mt-5 rounded-xl bg-court-50 p-4">
                <p class="text-sm font-semibold text-court-900">Open times you can promote</p>
                <p class="mt-1 text-xs text-court-700">Suggested from your opening hours and current bookings. Nothing is shown to players until you save it.</p>
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    <button v-for="slot in visibleOpportunities" :key="`${slot.resource_id}-${slot.slot_date}-${slot.starts_at_time}`" type="button" class="min-w-52 rounded-xl border border-court-200 bg-white p-3 text-left text-xs shadow-sm" @click="addSlot(slot)">
                        <span class="block font-semibold text-slate-900">{{ slot.resource_name }}</span><span class="mt-1 block text-slate-500">{{ slot.slot_date }} · {{ slot.starts_at_time }}–{{ slot.ends_at_time }}</span><span :class="['mt-2 block font-semibold', slot.is_last_minute ? 'text-amber-700' : 'text-court-700']">{{ slot.is_last_minute ? 'Last-minute opening' : 'Open upcoming time' }} · Add</span>
                    </button>
                </div>
            </div>

            <div v-if="form.slots.length" class="mt-5 space-y-4">
                <div v-for="(slot, index) in form.slots" :key="slot._key" class="grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-[1.4fr_1fr_1fr_1fr_auto] sm:items-end">
                    <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</span><AppSelect v-model="slot.resource_id" :options="slotResources()" class="mt-2" :aria-label="`Slot ${index + 1} court`" /><FormError :message="form.errors[`slots.${index}.resource_id`]" /></label>
                    <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</span><input v-model="slot.slot_date" type="date" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3"><FormError :message="form.errors[`slots.${index}.slot_date`]" /></label>
                    <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Start</span><input v-model="slot.starts_at_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3"><FormError :message="form.errors[`slots.${index}.starts_at_time`]" /></label>
                    <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">End</span><input v-model="slot.ends_at_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3"><FormError :message="form.errors[`slots.${index}.ends_at_time`]" /></label>
                    <button type="button" class="rounded-xl border border-red-200 px-3 py-3 text-sm font-semibold text-red-700" @click="removeSlot(index)">Remove</button>
                </div>
            </div>
            <div v-else class="mt-5 rounded-xl border border-dashed border-slate-300 px-5 py-8 text-center text-sm text-slate-500">No exact times selected. The date and time choices above will apply.</div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <label class="flex items-start gap-3"><input v-model="form.is_public" type="checkbox" class="mt-1 rounded border-slate-300"><span><strong class="block text-sm">Show on public site</strong><span class="text-sm text-slate-500">Active deals can appear in search, the deals page, and venue pages.</span></span></label>
        </section>

        <div class="flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-court-700 px-6 py-3 font-semibold text-white disabled:opacity-50">{{ promotion ? 'Save deal' : 'Create deal' }}</button></div>
    </form>
</template>
