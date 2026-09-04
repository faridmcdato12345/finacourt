<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    normalizePromotionPayload,
    PROMOTION_STRATEGY,
    promotionPricePreview,
    strategyForPromotionType,
} from '../lib/promotion-builder.js';
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
    serviceFee: { type: Object, default: null },
});

const steps = [
    { number: 1, short: 'What', label: 'What to promote' },
    { number: 2, short: 'When', label: 'Choose availability' },
    { number: 3, short: 'Offer', label: 'Set the offer' },
    { number: 4, short: 'Review', label: 'Review and publish' },
];
const strategies = [
    {
        value: PROMOTION_STRATEGY.EXACT,
        title: 'Specific court times',
        description: 'Choose exact open courts, dates, and hours. Best for filling unsold time.',
        eyebrow: 'Precise and flexible',
    },
    {
        value: PROMOTION_STRATEGY.RECURRING,
        title: 'Recurring slower hours',
        description: 'Run the same offer on selected weekdays and hours across a date range.',
        eyebrow: 'Repeat automatically',
    },
    {
        value: PROMOTION_STRATEGY.SCOPE,
        title: 'Entire venue or court',
        description: 'Apply an offer to every eligible time at one venue or one selected court.',
        eyebrow: 'Simple broad offer',
    },
];

let slotKey = 0;
const initialSlots = props.promotion?.slots || props.defaults.slots || [];
const strategy = ref(props.promotion || initialSlots.length > 0
    ? strategyForPromotionType(props.promotion?.promotion_type || props.defaults.promotion_type)
    : null);
const currentStep = ref(1);
const furthestStep = ref(props.promotion ? 4 : 1);
const stepNotice = ref('');
const form = useForm({
    venue_id: props.promotion?.venue_id || props.defaults.venue_id || props.venues[0]?.id || '',
    resource_id: props.promotion?.resource_id || props.defaults.resource_id || '',
    audience_sport_id: props.promotion?.audience_sport_id || props.defaults.audience_sport_id || '',
    title: props.promotion?.title || '',
    description: props.promotion?.description || '',
    goal: props.promotion?.goal || props.defaults.goal || 'fill_empty_slots',
    status: props.promotion?.status || props.defaults.status || 'draft',
    promotion_type: props.promotion?.promotion_type || props.defaults.promotion_type || 'venue',
    discount_type: props.promotion
        ? (props.promotion.discount_type || '')
        : 'percentage',
    discount_value: props.promotion?.discount_value || '',
    starts_on: props.promotion?.starts_on || props.defaults.starts_on || '',
    ends_on: props.promotion?.ends_on || props.defaults.ends_on || '',
    days_of_week: props.promotion?.days_of_week || [],
    starts_at_time: props.promotion?.starts_at_time || '',
    ends_at_time: props.promotion?.ends_at_time || '',
    slots: initialSlots.map((slot) => ({ ...slot, _key: ++slotKey })),
    is_public: props.promotion?.is_public ?? false,
});

const selectedVenue = computed(() => props.venues.find(
    (venue) => String(venue.id) === String(form.venue_id),
));
const resources = computed(() => selectedVenue.value?.resources || []);
const activeResources = computed(() => resources.value.filter((resource) => resource.is_active));
const selectedResource = computed(() => resources.value.find(
    (resource) => String(resource.id) === String(form.resource_id),
));
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
const previewResource = computed(() => {
    if (strategy.value === PROMOTION_STRATEGY.EXACT && form.slots.length > 0) {
        return resources.value.find(
            (resource) => String(resource.id) === String(form.slots[0].resource_id),
        );
    }

    return selectedResource.value || activeResources.value[0] || null;
});
const pricePreview = computed(() => previewResource.value
    ? promotionPricePreview({
        baseHourlyRate: previewResource.value.base_hourly_rate,
        discountType: form.discount_type || null,
        discountValue: form.discount_value || null,
        serviceFee: props.serviceFee,
    })
    : null);
const strategyDetails = computed(() => strategies.find((option) => option.value === strategy.value));
const statusDetails = computed(() => props.statuses.find((option) => option.value === form.status));
const availabilitySummary = computed(() => {
    if (strategy.value === PROMOTION_STRATEGY.EXACT) {
        return `${form.slots.length} exact court ${form.slots.length === 1 ? 'time' : 'times'} selected`;
    }

    if (strategy.value === PROMOTION_STRATEGY.RECURRING) {
        const days = form.days_of_week.length
            ? `${form.days_of_week.length} selected ${form.days_of_week.length === 1 ? 'day' : 'days'}`
            : 'Every day';

        return `${days}, ${form.starts_at_time || '—'}–${form.ends_at_time || '—'}`;
    }

    return selectedResource.value
        ? `Every eligible time on ${selectedResource.value.name}`
        : 'Every eligible time at the venue';
});
const offerLabel = computed(() => {
    if (form.discount_type === 'percentage' && Number(form.discount_value) > 0) {
        return `${Number(form.discount_value)}% off`;
    }

    if (form.discount_type === 'fixed_hourly_rate' && Number(form.discount_value) > 0) {
        return `${money(Number(form.discount_value))} special hourly price`;
    }

    return 'Promotional visibility without a price discount';
});
const finalActionLabel = computed(() => {
    if (form.status === 'draft') return props.promotion ? 'Save as draft' : 'Save draft';
    if (form.status === 'paused') return 'Save and pause deal';
    if (form.status === 'cancelled') return 'Cancel deal';
    if (form.status === 'completed') return 'Mark deal completed';

    return props.promotion ? 'Save and publish' : 'Publish deal';
});

function chooseStrategy(value) {
    strategy.value = value;
    stepNotice.value = '';

    if (value === PROMOTION_STRATEGY.EXACT
        && !['promote_specific_slots', 'promote_today_or_tonight'].includes(form.goal)) {
        form.goal = 'promote_specific_slots';
    } else if (value === PROMOTION_STRATEGY.RECURRING) {
        form.goal = 'increase_off_peak_bookings';
    } else if (value === PROMOTION_STRATEGY.SCOPE && form.goal !== 'get_new_customers') {
        form.goal = 'fill_empty_slots';
    }
}

function venueChanged() {
    const eligibleIds = new Set(resources.value.map((resource) => String(resource.id)));

    if (!eligibleIds.has(String(form.resource_id))) form.resource_id = '';
    if (!sports.value.some((sport) => String(sport.value) === String(form.audience_sport_id))) {
        form.audience_sport_id = '';
    }
    form.slots = form.slots.filter((slot) => eligibleIds.has(String(slot.resource_id)));
}

function addSlot(opportunity = null) {
    const resourceId = opportunity?.resource_id
        || activeResources.value[0]?.id
        || '';
    const date = opportunity?.slot_date || form.starts_on;
    const start = opportunity?.starts_at_time || '18:00';
    const end = opportunity?.ends_at_time || '19:00';

    if (form.slots.some((slot) => String(slot.resource_id) === String(resourceId)
        && slot.slot_date === date
        && slot.starts_at_time === start
        && slot.ends_at_time === end)) return;

    form.slots.push({
        id: null,
        resource_id: resourceId,
        slot_date: date,
        starts_at_time: start,
        ends_at_time: end,
        _key: ++slotKey,
    });
    chooseStrategy(PROMOTION_STRATEGY.EXACT);

    if (opportunity?.is_last_minute) form.goal = 'promote_today_or_tonight';
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

function isSlotSelected(opportunity) {
    return form.slots.some((slot) => String(slot.resource_id) === String(opportunity.resource_id)
        && slot.slot_date === opportunity.slot_date
        && slot.starts_at_time === opportunity.starts_at_time
        && slot.ends_at_time === opportunity.ends_at_time);
}

function selectOffer(type) {
    form.discount_type = type;

    if (!type) form.discount_value = '';
    else if (!form.discount_value) form.discount_value = type === 'percentage' ? '20' : '';
}

function selectStatus(value) {
    form.status = value;
    form.is_public = ['scheduled', 'active'].includes(value);
}

function statusActionLabel(status) {
    return {
        draft: 'Keep as draft',
        scheduled: 'Publish on schedule',
        active: 'Publish now',
        paused: 'Pause this deal',
        completed: 'Mark completed',
        cancelled: 'Cancel this deal',
    }[status.value] || status.label;
}

function statusDescription(status) {
    return {
        draft: 'Only you can see it. Return and publish when it is ready.',
        scheduled: 'Keep it hidden until its start date, then publish it automatically.',
        active: 'Show it to players immediately so they can book eligible future dates and times.',
        paused: 'Temporarily stop new players from receiving this deal.',
        completed: 'Close the deal because its campaign has finished.',
        cancelled: 'Permanently cancel this deal.',
    }[status.value] || '';
}

function stepIssue(step) {
    if (step === 1 && !strategy.value) return 'Choose what you want to promote.';

    if (step === 2) {
        if (!form.venue_id) return 'Choose a venue before continuing.';

        if (strategy.value === PROMOTION_STRATEGY.EXACT) {
            if (form.slots.length === 0) return 'Choose at least one exact court time.';
            if (form.slots.some((slot) => !slot.resource_id || !slot.slot_date || !slot.starts_at_time || !slot.ends_at_time)) {
                return 'Complete the court, date, start, and end for every selected time.';
            }
        } else {
            if (!form.starts_on || !form.ends_on) return 'Choose the start and end dates.';
            if (form.ends_on < form.starts_on) return 'The end date must be on or after the start date.';
        }

        if (strategy.value === PROMOTION_STRATEGY.RECURRING
            && (!form.starts_at_time || !form.ends_at_time)) {
            return 'Choose the daily start and end time for the recurring offer.';
        }
    }

    if (step === 3) {
        if (!form.title.trim()) return 'Give the deal a short title.';

        if (form.discount_type) {
            const value = Number(form.discount_value);

            if (!Number.isFinite(value) || value <= 0) return 'Enter a valid discount value.';
            if (form.discount_type === 'percentage' && value > 100) return 'Percentage discounts cannot exceed 100%.';
        }
    }

    return '';
}

function nextStep() {
    const issue = stepIssue(currentStep.value);

    if (issue) {
        stepNotice.value = issue;
        return;
    }

    stepNotice.value = '';
    currentStep.value = Math.min(4, currentStep.value + 1);
    furthestStep.value = Math.max(furthestStep.value, currentStep.value);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function previousStep() {
    stepNotice.value = '';
    currentStep.value = Math.max(1, currentStep.value - 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep(step) {
    if (step > furthestStep.value) return;

    currentStep.value = step;
    stepNotice.value = '';
}

function firstErrorStep(errors) {
    const fields = Object.keys(errors);

    if (fields.some((field) => field === 'venue_id'
        || field === 'resource_id'
        || field === 'audience_sport_id'
        || field === 'starts_on'
        || field === 'ends_on'
        || field === 'days_of_week'
        || field === 'starts_at_time'
        || field === 'ends_at_time'
        || field === 'slots'
        || field.startsWith('slots.'))) return 2;
    if (fields.some((field) => field === 'title'
        || field === 'description'
        || field === 'discount_type'
        || field === 'discount_value')) return 3;

    return 4;
}

function submit() {
    for (const step of [1, 2, 3]) {
        const issue = stepIssue(step);

        if (issue) {
            currentStep.value = step;
            stepNotice.value = issue;
            return;
        }
    }

    stepNotice.value = '';
    form.transform((data) => normalizePromotionPayload(data, strategy.value));
    const options = {
        preserveScroll: true,
        onError: (errors) => {
            currentStep.value = firstErrorStep(errors);
            furthestStep.value = 4;
        },
    };

    if (props.promotion) form.put(`/owner/promotions/${props.promotion.id}`, options);
    else form.post('/owner/promotions', options);
}

function money(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <nav class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm" aria-label="Deal creation progress">
            <ol class="grid grid-cols-4 gap-1">
                <li v-for="stepItem in steps" :key="stepItem.number">
                    <button type="button" :disabled="stepItem.number > furthestStep" :aria-current="currentStep === stepItem.number ? 'step' : undefined" :class="['flex w-full items-center gap-2 rounded-xl px-2 py-3 text-left transition sm:px-3', currentStep === stepItem.number ? 'bg-court-50 text-court-900' : stepItem.number <= furthestStep ? 'text-slate-600 hover:bg-slate-50' : 'cursor-not-allowed text-slate-300']" @click="goToStep(stepItem.number)">
                        <span :class="['grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold', currentStep === stepItem.number ? 'bg-court-700 text-white' : stepItem.number < currentStep ? 'bg-court-100 text-court-800' : 'bg-slate-100 text-slate-500']">{{ stepItem.number < currentStep ? '✓' : stepItem.number }}</span>
                        <span class="hidden text-xs font-semibold sm:block lg:text-sm">{{ stepItem.label }}</span>
                        <span class="text-xs font-semibold sm:hidden">{{ stepItem.short }}</span>
                    </button>
                </li>
            </ol>
        </nav>

        <div v-if="stepNotice" role="alert" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{{ stepNotice }}</div>

        <section v-show="currentStep === 1" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Step 1 of 4</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight">What do you want to promote?</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Choose one simple strategy. FinACourt will show only the availability settings that strategy needs.</p>
            <div class="mt-7 grid gap-4 lg:grid-cols-3">
                <button v-for="option in strategies" :key="option.value" type="button" :aria-pressed="strategy === option.value" :class="['group rounded-2xl border p-5 text-left transition', strategy === option.value ? 'border-court-500 bg-court-50 ring-2 ring-court-100' : 'border-slate-200 hover:border-court-300 hover:bg-court-50/30']" @click="chooseStrategy(option.value)">
                    <span class="flex items-start justify-between gap-4"><span><span class="text-xs font-semibold uppercase tracking-wider text-court-700">{{ option.eyebrow }}</span><strong class="mt-3 block text-lg text-slate-950">{{ option.title }}</strong></span><span :class="['grid size-7 shrink-0 place-items-center rounded-full border text-sm font-bold', strategy === option.value ? 'border-court-700 bg-court-700 text-white' : 'border-slate-300 text-transparent']">✓</span></span>
                    <span class="mt-3 block text-sm leading-6 text-slate-500">{{ option.description }}</span>
                </button>
            </div>
        </section>

        <section v-show="currentStep === 2" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Step 2 of 4</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight">Choose the availability</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Only bookings fully inside these courts and times can receive the offer.</p>
            <div class="mt-7 grid gap-5 sm:grid-cols-2">
                <label class="sm:col-span-2"><span class="text-sm font-medium">Venue</span><AppSelect v-model="form.venue_id" :options="venues" option-value="id" option-label="name" class="mt-2" aria-label="Venue for this deal" @change="venueChanged" /><FormError :message="form.errors.venue_id" /></label>
                <label v-if="strategy !== PROMOTION_STRATEGY.EXACT" class="sm:col-span-2"><span class="text-sm font-medium">Applies to</span><AppSelect v-model="form.resource_id" :options="[{ value: '', label: 'Entire venue — all bookable courts' }, ...resources.map((resource) => ({ value: resource.id, label: `${resource.name} · ${resource.sport} · ${money(resource.base_hourly_rate)}/hour${resource.is_active ? '' : ' · not bookable'}`, disabled: !resource.is_active }))]" class="mt-2" aria-label="Court scope for this deal" /><FormError :message="form.errors.resource_id" /></label>
                <template v-if="strategy !== PROMOTION_STRATEGY.EXACT">
                    <label><span class="text-sm font-medium">Starts on</span><input v-model="form.starts_on" type="date" required class="app-date-input mt-2"><FormError :message="form.errors.starts_on" /></label>
                    <label><span class="text-sm font-medium">Ends on</span><input v-model="form.ends_on" type="date" required class="app-date-input mt-2"><FormError :message="form.errors.ends_on" /></label>
                </template>
                <template v-if="strategy === PROMOTION_STRATEGY.RECURRING">
                    <div class="sm:col-span-2"><span class="text-sm font-medium">Repeat on <span class="font-normal text-slate-400">leave blank for every day</span></span><div class="mt-3 flex flex-wrap gap-2"><label v-for="day in weekdays" :key="day.value" :class="['flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2.5 text-sm', form.days_of_week.includes(day.value) ? 'border-court-400 bg-court-50 text-court-900' : 'border-slate-200']"><input v-model="form.days_of_week" type="checkbox" :value="day.value" class="rounded border-slate-300">{{ day.label }}</label></div><FormError :message="form.errors.days_of_week" /></div>
                    <label><span class="text-sm font-medium">Daily start time</span><input v-model="form.starts_at_time" type="time" class="app-time-input mt-2"><FormError :message="form.errors.starts_at_time" /></label>
                    <label><span class="text-sm font-medium">Daily end time</span><input v-model="form.ends_at_time" type="time" class="app-time-input mt-2"><FormError :message="form.errors.ends_at_time" /></label>
                </template>
                <label v-if="strategy !== PROMOTION_STRATEGY.EXACT && !form.resource_id && sports.length > 1" class="sm:col-span-2"><span class="text-sm font-medium">Player sport <span class="font-normal text-slate-400">optional</span></span><AppSelect v-model="form.audience_sport_id" :options="[{ value: '', label: 'Any sport covered by this deal' }, ...sports]" class="mt-2" aria-label="Sport this deal is for" /><p class="mt-2 text-xs text-slate-500">Use this only when a whole-venue offer should be limited to one sport.</p><FormError :message="form.errors.audience_sport_id" /></label>
            </div>

            <div v-if="strategy === PROMOTION_STRATEGY.EXACT" class="mt-7 space-y-5">
                <div v-if="visibleOpportunities.length" class="rounded-2xl bg-court-50 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="font-semibold text-court-950">Recommended open times</h3><p class="mt-1 text-xs leading-5 text-court-700">Calculated from your opening hours and current bookings. Nothing is published until the final step.</p></div><span class="text-xs font-semibold text-court-700">{{ form.slots.length }} selected</span></div>
                    <div class="court-carousel mt-4 flex snap-x gap-3 overflow-x-auto pb-2">
                        <button v-for="slot in visibleOpportunities" :key="`${slot.resource_id}-${slot.slot_date}-${slot.starts_at_time}`" type="button" :disabled="isSlotSelected(slot)" :class="['min-w-60 snap-start rounded-xl border bg-white p-4 text-left shadow-sm', isSlotSelected(slot) ? 'border-court-400 ring-2 ring-court-100' : 'border-court-200 hover:border-court-400']" @click="addSlot(slot)">
                            <span class="flex items-start justify-between gap-3"><span><strong class="block text-sm text-slate-900">{{ slot.resource_name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ slot.sport_name }}</span></span><span v-if="slot.is_last_minute" class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Soon</span></span>
                            <span class="mt-4 block text-sm font-semibold text-slate-900">{{ slot.slot_date }}</span><span class="mt-1 block text-xs text-slate-500">{{ slot.starts_at_time }}–{{ slot.ends_at_time }} · {{ money(slot.estimated_value) }}</span><span class="mt-4 block text-xs font-semibold text-court-700">{{ isSlotSelected(slot) ? '✓ Added' : '+ Add this time' }}</span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4"><div><h3 class="font-semibold">Selected court times</h3><p class="mt-1 text-xs text-slate-500">Players may book any available duration fully inside a selected window.</p></div><button type="button" class="shrink-0 rounded-xl border border-court-300 px-4 py-2.5 text-sm font-semibold text-court-800" @click="addSlot()">Add custom time</button></div>
                <FormError :message="form.errors.slots" />
                <div v-if="form.slots.length" class="space-y-3">
                    <div v-for="(slot, index) in form.slots" :key="slot._key" class="grid gap-4 rounded-2xl border border-slate-200 p-4 sm:grid-cols-[1.4fr_1fr_1fr_1fr_auto] sm:items-end">
                        <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</span><AppSelect v-model="slot.resource_id" :options="slotResources()" class="mt-2" :aria-label="`Selected time ${index + 1} court`" /><FormError :message="form.errors[`slots.${index}.resource_id`]" /></label>
                        <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</span><input v-model="slot.slot_date" type="date" class="app-date-input mt-2"><FormError :message="form.errors[`slots.${index}.slot_date`]" /></label>
                        <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Start</span><input v-model="slot.starts_at_time" type="time" class="app-time-input mt-2"><FormError :message="form.errors[`slots.${index}.starts_at_time`]" /></label>
                        <label><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">End</span><input v-model="slot.ends_at_time" type="time" class="app-time-input mt-2"><FormError :message="form.errors[`slots.${index}.ends_at_time`]" /></label>
                        <button type="button" class="rounded-xl border border-red-200 px-3 py-3 text-sm font-semibold text-red-700" @click="removeSlot(index)">Remove</button>
                    </div>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-300 px-5 py-10 text-center text-sm text-slate-500">Choose a recommended time above or add a custom court time.</div>
            </div>
        </section>

        <section v-show="currentStep === 3" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Step 3 of 4</p><h2 class="mt-2 text-2xl font-semibold tracking-tight">Set the offer</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Give players a clear reason to book. Final prices are always recalculated securely by the server.</p>
            <div class="mt-7 grid gap-5 sm:grid-cols-2"><label class="sm:col-span-2"><span class="text-sm font-medium">Deal title</span><input v-model="form.title" required maxlength="255" placeholder="Example: 30% off Friday morning" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><FormError :message="form.errors.title" /></label><label class="sm:col-span-2"><span class="text-sm font-medium">Short description <span class="font-normal text-slate-400">optional</span></span><textarea v-model="form.description" rows="3" maxlength="2000" placeholder="Tell players why this is a good time to book." class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></textarea><FormError :message="form.errors.description" /></label></div>
            <fieldset class="mt-7"><legend class="text-sm font-semibold">What will players receive?</legend><div class="mt-3 grid gap-3 sm:grid-cols-3"><button type="button" :aria-pressed="form.discount_type === 'percentage'" :class="['rounded-2xl border p-4 text-left', form.discount_type === 'percentage' ? 'border-court-500 bg-court-50 ring-2 ring-court-100' : 'border-slate-200']" @click="selectOffer('percentage')"><strong class="block text-sm">Percentage discount</strong><span class="mt-2 block text-xs leading-5 text-slate-500">Take a percentage off the normal court price.</span></button><button type="button" :aria-pressed="form.discount_type === 'fixed_hourly_rate'" :class="['rounded-2xl border p-4 text-left', form.discount_type === 'fixed_hourly_rate' ? 'border-court-500 bg-court-50 ring-2 ring-court-100' : 'border-slate-200']" @click="selectOffer('fixed_hourly_rate')"><strong class="block text-sm">Special hourly price</strong><span class="mt-2 block text-xs leading-5 text-slate-500">Replace the normal hourly price with a lower one.</span></button><button type="button" :aria-pressed="!form.discount_type" :class="['rounded-2xl border p-4 text-left', !form.discount_type ? 'border-court-500 bg-court-50 ring-2 ring-court-100' : 'border-slate-200']" @click="selectOffer('')"><strong class="block text-sm">Promote without discount</strong><span class="mt-2 block text-xs leading-5 text-slate-500">Highlight the availability while keeping the normal price.</span></button></div></fieldset>
            <div v-if="form.discount_type" class="mt-5 max-w-xl"><label><span class="text-sm font-medium">{{ form.discount_type === 'percentage' ? 'Percentage off' : 'Special hourly price' }}</span><div class="relative mt-2"><span v-if="form.discount_type === 'fixed_hourly_rate'" class="absolute inset-y-0 left-4 flex items-center font-semibold text-slate-500">₱</span><input v-model="form.discount_value" type="number" min="0" :max="form.discount_type === 'percentage' ? 100 : 999999.99" step="0.01" :class="['w-full rounded-xl border border-slate-300 py-3 pr-12', form.discount_type === 'fixed_hourly_rate' ? 'pl-9' : 'pl-4']"><span v-if="form.discount_type === 'percentage'" class="absolute inset-y-0 right-4 flex items-center font-semibold text-slate-500">%</span></div><FormError :message="form.errors.discount_value" /></label></div>
            <section v-if="pricePreview" class="mt-7 overflow-hidden rounded-2xl bg-court-950 text-white"><div class="border-b border-white/10 px-5 py-4"><p class="text-xs font-semibold uppercase tracking-wider text-court-300">One-hour price example</p><h3 class="mt-1 font-semibold">{{ previewResource.name }}</h3></div><dl class="grid gap-4 p-5 sm:grid-cols-4"><div><dt class="text-xs text-slate-400">Normal court price</dt><dd class="mt-1 text-lg font-semibold">{{ money(pricePreview.originalTotal) }}</dd></div><div><dt class="text-xs text-slate-400">Deal court price</dt><dd class="mt-1 text-lg font-semibold text-court-300">{{ money(pricePreview.venueTotal) }}</dd></div><div><dt class="text-xs text-slate-400">FinACourt service fee</dt><dd class="mt-1 text-lg font-semibold">{{ money(pricePreview.serviceFee) }}</dd></div><div><dt class="text-xs text-slate-400">Estimated player total</dt><dd class="mt-1 text-xl font-semibold">{{ money(pricePreview.playerTotal) }}</dd></div></dl><p class="px-5 pb-5 text-xs leading-5 text-slate-400">This is an estimate for one hour. The booking server calculates the final amount from the exact court, duration, and active service-fee rule.</p></section>
            <p v-else class="mt-7 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">Choose a venue with a bookable court to see a price example.</p>
        </section>

        <section v-show="currentStep === 4" class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Step 4 of 4</p><h2 class="mt-2 text-2xl font-semibold tracking-tight">Review and publish</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Check exactly what players can receive, then choose whether to save or publish it.</p>
                <div class="mt-7 grid gap-4 lg:grid-cols-2">
                    <section class="rounded-2xl border border-slate-200 p-5"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">What</p><h3 class="mt-2 font-semibold">{{ strategyDetails?.title }}</h3></div><button type="button" class="text-xs font-semibold text-court-700" @click="goToStep(1)">Edit</button></div><p class="mt-3 text-sm text-slate-600">{{ selectedVenue?.name || 'No venue selected' }}<span v-if="selectedResource"> · {{ selectedResource.name }}</span></p></section>
                    <section class="rounded-2xl border border-slate-200 p-5"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Availability</p><h3 class="mt-2 font-semibold">{{ availabilitySummary }}</h3></div><button type="button" class="text-xs font-semibold text-court-700" @click="goToStep(2)">Edit</button></div><p v-if="strategy !== PROMOTION_STRATEGY.EXACT" class="mt-3 text-sm text-slate-600">{{ form.starts_on }} through {{ form.ends_on }}</p><div v-else class="mt-3 space-y-1 text-sm text-slate-600"><p v-for="slot in form.slots.slice(0, 4)" :key="slot._key">{{ resources.find((resource) => String(resource.id) === String(slot.resource_id))?.name }} · {{ slot.slot_date }} · {{ slot.starts_at_time }}–{{ slot.ends_at_time }}</p><p v-if="form.slots.length > 4" class="text-xs text-slate-400">+ {{ form.slots.length - 4 }} more selected times</p></div></section>
                    <section class="rounded-2xl border border-slate-200 p-5 lg:col-span-2"><div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Offer</p><h3 class="mt-2 text-xl font-semibold">{{ form.title || 'Untitled deal' }}</h3></div><button type="button" class="text-xs font-semibold text-court-700" @click="goToStep(3)">Edit</button></div><p class="mt-2 font-semibold text-court-700">{{ offerLabel }}</p><p v-if="form.description" class="mt-3 text-sm leading-6 text-slate-500">{{ form.description }}</p></section>
                </div>
                <section v-if="pricePreview" class="mt-4 rounded-2xl bg-court-950 p-5 text-white"><div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-300">Player price example</p><p class="mt-2 text-sm text-slate-300">One hour on {{ previewResource.name }}</p></div><div class="sm:text-right"><p class="text-xs text-slate-400">Estimated player total</p><p class="mt-1 text-3xl font-semibold">{{ money(pricePreview.playerTotal) }}</p><p class="mt-1 text-xs text-slate-400">{{ money(pricePreview.venueTotal) }} court price + {{ money(pricePreview.serviceFee) }} service fee</p></div></div></section>
            </div>
            <fieldset class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"><legend class="px-1 text-lg font-semibold">What should happen when you save?</legend><div class="mt-4 grid gap-3 lg:grid-cols-3"><button v-for="status in statuses" :key="status.value" type="button" :aria-pressed="form.status === status.value" :class="['rounded-2xl border p-4 text-left transition', form.status === status.value ? 'border-court-500 bg-court-50 ring-2 ring-court-100' : 'border-slate-200 hover:border-court-300']" @click="selectStatus(status.value)"><span class="flex items-start justify-between gap-3"><strong class="text-sm">{{ statusActionLabel(status) }}</strong><span :class="['grid size-6 place-items-center rounded-full border text-xs', form.status === status.value ? 'border-court-700 bg-court-700 text-white' : 'border-slate-300 text-transparent']">✓</span></span><span class="mt-2 block text-xs leading-5 text-slate-500">{{ statusDescription(status) }}</span></button></div><FormError :message="form.errors.status" /><div class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"><strong>{{ statusDetails?.label || 'Choose a state' }}:</strong> {{ form.status === 'draft' ? 'players cannot see or use this deal.' : form.status === 'scheduled' ? 'players will not see the deal before its start date.' : form.status === 'active' ? 'players can see it now, but its discount applies only to eligible dates, courts, and times.' : 'the deal will not accept new bookings.' }}</div></fieldset>
        </section>

        <div class="sticky bottom-4 z-10 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur sm:px-4"><button v-if="currentStep > 1" type="button" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="previousStep">← Back</button><span v-else class="text-xs text-slate-400">Step {{ currentStep }} of 4</span><button v-if="currentStep < 4" type="button" class="rounded-xl bg-court-700 px-5 py-2.5 text-sm font-semibold text-white" @click="nextStep">Continue →</button><button v-else :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ finalActionLabel }}</button></div>
    </form>
</template>
