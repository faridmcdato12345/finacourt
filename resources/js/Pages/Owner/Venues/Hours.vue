<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object, hours: Array });
const form = useForm({ hours: props.hours.map((hour) => ({ ...hour })) });

function submit() {
    form.put(`/owner/venues/${props.venue.id}/hours`);
}

function closedChanged(hour) {
    if (hour.is_closed) hour.is_24_hours = false;
}

function allDayChanged(hour) {
    if (hour.is_24_hours) hour.is_closed = false;
}

function spansMidnight(hour) {
    return !hour.is_closed
        && !hour.is_24_hours
        && hour.opens_at
        && hour.closes_at
        && hour.closes_at < hour.opens_at;
}
</script>

<template>
    <Head :title="`${venue.name} hours`" />
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <Link :href="`/owner/venues/${venue.id}`" class="text-sm font-semibold text-court-700">← Back to venue</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Opening hours</h2>
            <p class="mt-2 text-slate-600">Set regular weekly hours for {{ venue.name }}. Closing times earlier than opening times are treated as the following day.</p>

            <form class="mt-8" @submit.prevent="submit">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div v-for="(hour, index) in form.hours" :key="hour.day_of_week" class="grid gap-4 border-b border-slate-100 p-5 last:border-0 sm:grid-cols-[8rem_6rem_9rem_1fr_1fr] sm:items-start">
                        <p class="pt-2 font-semibold text-slate-900">{{ hour.day }}</p>
                        <label class="flex items-center gap-2 pt-2 text-sm text-slate-600"><input v-model="hour.is_closed" type="checkbox" class="size-4 rounded border-slate-300 text-court-700" @change="closedChanged(hour)" /> Closed</label>
                        <div>
                            <label class="flex items-center gap-2 pt-2 text-sm text-slate-600"><input v-model="hour.is_24_hours" type="checkbox" class="size-4 rounded border-slate-300 text-court-700" @change="allDayChanged(hour)" /> Open 24 hours</label>
                            <FormError :message="form.errors[`hours.${index}.is_24_hours`]" />
                        </div>
                        <div>
                            <label :for="`opens-${index}`" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-slate-400">Opens</label>
                            <input :id="`opens-${index}`" v-model="hour.opens_at" type="time" :disabled="hour.is_closed || hour.is_24_hours" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 disabled:bg-slate-100 disabled:text-slate-400" />
                            <FormError :message="form.errors[`hours.${index}.opens_at`]" />
                        </div>
                        <div>
                            <label :for="`closes-${index}`" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-slate-400">Closes</label>
                            <input :id="`closes-${index}`" v-model="hour.closes_at" type="time" :disabled="hour.is_closed || hour.is_24_hours" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 disabled:bg-slate-100 disabled:text-slate-400" />
                            <p v-if="spansMidnight(hour)" class="mt-1.5 text-xs font-medium text-court-700">Closes the next day</p>
                            <FormError :message="form.errors[`hours.${index}.closes_at`]" />
                        </div>
                    </div>
                </div>
                <FormError :message="form.errors.hours" />
                <div class="mt-6 flex justify-end"><button type="submit" :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800 disabled:opacity-60">{{ form.processing ? 'Saving…' : 'Save hours' }}</button></div>
            </form>
        </div>
    </OwnerLayout>
</template>
