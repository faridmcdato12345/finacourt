<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    resources: Array,
    timezone: String,
    defaultDate: String,
    defaultHoldMinutes: Number,
    maximumHoldMinutes: Number,
    statuses: Array,
    sources: Array,
});

const form = useForm({
    resource_id: props.resources[0]?.id || '',
    booking_date: props.defaultDate,
    start_time: '',
    end_time: '',
    status: 'confirmed',
    source: 'manual',
    hold_minutes: props.defaultHoldMinutes,
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    notes: '',
});
const duration = ref(props.resources[0]?.booking_increment_minutes || 60);
const schedule = ref(null);
const availabilityError = ref('');
const checking = ref(false);
const selectedResource = computed(() => props.resources.find((item) => String(item.id) === String(form.resource_id)));
const durations = computed(() => {
    const increment = selectedResource.value?.booking_increment_minutes || 60;
    return Array.from({ length: Math.max(1, Math.floor(240 / increment)) }, (_, index) => increment * (index + 1));
});
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function resourceChanged() {
    duration.value = selectedResource.value?.booking_increment_minutes || 60;
    schedule.value = null;
}

async function checkAvailability() {
    if (!form.resource_id || !form.booking_date) return;
    checking.value = true;
    availabilityError.value = '';
    schedule.value = null;
    const params = new URLSearchParams({
        resource_id: form.resource_id,
        date: form.booking_date,
        duration_minutes: duration.value,
    });
    try {
        const response = await fetch(`/owner/bookings/availability?${params}`, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || 'Available times could not be loaded.');
        schedule.value = payload;
    } catch (error) {
        availabilityError.value = error.message;
    } finally {
        checking.value = false;
    }
}

function chooseSlot(slot) {
    if (!slot.available) return;
    form.start_time = slot.start_time;
    form.end_time = slot.end_time;
}

function submit() {
    form.post('/owner/bookings');
}
</script>

<template>
    <Head title="Create booking" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <Link href="/owner/bookings" class="text-sm font-semibold text-court-700">← Bookings</Link>
            <div class="mt-4">
                <h2 class="text-3xl font-semibold tracking-tight text-slate-950">Create a booking</h2>
                <p class="mt-2 text-sm text-slate-500">Manual, phone, Messenger, and walk-in bookings all use the same double-booking protection. Times use {{ timezone }}.</p>
            </div>

            <div v-if="!resources.length" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                Add and turn on a court before creating bookings.
            </div>

            <form v-else class="mt-8 grid gap-6 lg:grid-cols-[1fr_23rem]" @submit.prevent="submit">
                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-slate-950">Court and time</h3>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <label class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Court</span><AppSelect v-model="form.resource_id" :options="resources.map((resource) => ({ value: resource.id, label: `${resource.venue} — ${resource.name} (${resource.sport})` }))" class="mt-2" aria-label="Booking court" @change="resourceChanged" /><FormError :message="form.errors.resource_id" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Date</span><input v-model="form.booking_date" type="date" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.booking_date" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Slot duration</span><AppSelect v-model="duration" :options="durations.map((minutes) => ({ value: minutes, label: `${minutes} minutes` }))" class="mt-2" aria-label="Slot duration" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Start time</span><input v-model="form.start_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.start_time" /></label>
                            <label><span class="text-sm font-medium text-slate-700">End time</span><input v-model="form.end_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.end_time" /></label>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-slate-950">Player details</h3>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <label class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Customer name</span><input v-model="form.customer_name" type="text" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.customer_name" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Phone</span><input v-model="form.customer_phone" type="text" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.customer_phone" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Email</span><input v-model="form.customer_email" type="email" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.customer_email" /></label>
                            <label><span class="text-sm font-medium text-slate-700">How did they book?</span><AppSelect v-model="form.source" :options="sources" class="mt-2" aria-label="How did they book" /><FormError :message="form.errors.source" /></label>
                            <label><span class="text-sm font-medium text-slate-700">Booking status</span><AppSelect v-model="form.status" :options="statuses" class="mt-2" aria-label="Booking status" /><FormError :message="form.errors.status" /></label>
                            <label v-if="form.status === 'hold'"><span class="text-sm font-medium text-slate-700">Hold for minutes</span><input v-model="form.hold_minutes" type="number" min="1" :max="maximumHoldMinutes" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5"><FormError :message="form.errors.hold_minutes" /></label>
                            <label class="sm:col-span-2"><span class="text-sm font-medium text-slate-700">Notes</span><textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5" /><FormError :message="form.errors.notes" /></label>
                        </div>
                    </section>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-slate-950">Available times</h3>
                        <p class="mt-2 text-xs leading-5 text-slate-500">This is only a preview. FinACourt checks again when you save so double bookings are blocked.</p>
                        <button type="button" class="mt-4 w-full rounded-xl border border-court-700 px-4 py-2.5 text-sm font-semibold text-court-800" :disabled="checking" @click="checkAvailability">{{ checking ? 'Checking…' : 'Check available slots' }}</button>
                        <p v-if="availabilityError" class="mt-3 text-sm text-red-600">{{ availabilityError }}</p>
                        <div v-if="schedule" class="mt-4">
                            <p class="text-xs font-medium text-slate-500">{{ schedule.is_open ? `Open ${schedule.opens_at}–${schedule.closes_at}` : 'Closed or court not bookable' }}</p>
                            <div v-if="schedule.slots.length" class="mt-3 grid grid-cols-2 gap-2">
                                <button v-for="slot in schedule.slots" :key="slot.start_time" type="button" :disabled="!slot.available" :class="['rounded-lg border px-2 py-2 text-xs font-semibold', slot.available ? 'border-court-200 text-court-800 hover:bg-court-50' : 'border-slate-100 bg-slate-50 text-slate-300', form.start_time === slot.start_time && form.end_time === slot.end_time ? 'ring-2 ring-court-500' : '']" @click="chooseSlot(slot)">{{ slot.start_time }}–{{ slot.end_time }}</button>
                            </div>
                        </div>
                    </section>
                    <section v-if="selectedResource" class="rounded-2xl bg-slate-950 p-5 text-white">
                        <p class="text-xs uppercase tracking-wider text-slate-400">Normal hourly price</p>
                        <p class="mt-2 text-2xl font-semibold">{{ money.format(selectedResource.base_hourly_rate) }}<span class="text-sm font-normal text-slate-400"> / hour</span></p>
                        <p class="mt-2 text-xs leading-5 text-slate-400">FinACourt calculates and saves the final total when you create the booking.</p>
                    </section>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Creating…' : 'Create booking' }}</button>
                </aside>
            </form>
        </div>
    </OwnerLayout>
</template>
