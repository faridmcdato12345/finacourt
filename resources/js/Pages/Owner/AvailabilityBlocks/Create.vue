<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    resources: Array,
    timezone: String,
    defaultDate: String,
});

const suggestions = [
    'Court maintenance',
    'Private event',
    'Weather or safety concern',
    'Owner use',
];
const repeatUntil = new Date(`${props.defaultDate}T12:00:00`);
repeatUntil.setDate(repeatUntil.getDate() + 28);

const form = useForm({
    resource_id: props.resources[0]?.id || '',
    block_date: props.defaultDate,
    is_all_day: false,
    start_time: '08:00',
    end_time: '09:00',
    reason: '',
    repeat: 'none',
    repeat_until: repeatUntil.toISOString().slice(0, 10),
});

function useReason(reason) {
    form.reason = reason;
}

function submit() {
    form.post('/owner/bookings/blocks');
}
</script>

<template>
    <Head title="Block court time" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <Link href="/owner/bookings" class="text-sm font-semibold text-court-700">← Bookings</Link>
            <div class="mt-4">
                <p class="eyebrow">Manage availability</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Block court time</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Temporarily prevent players and staff from booking a court. Times use {{ timezone }}.</p>
            </div>

            <div v-if="!resources.length" class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                Add and turn on a court before blocking time.
            </div>

            <form v-else class="mt-8 grid gap-6 lg:grid-cols-[1fr_20rem]" @submit.prevent="submit">
                <div class="space-y-6">
                    <section class="app-card p-6 sm:p-7">
                        <h3 class="text-lg font-semibold text-slate-950">Court and time</h3>
                        <p class="mt-1 text-sm text-slate-500">Choose exactly where and when bookings should be disabled.</p>

                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            <label class="sm:col-span-2">
                                <span class="text-sm font-medium text-slate-700">Court</span>
                                <AppSelect
                                    v-model="form.resource_id"
                                    :options="resources.map((resource) => ({ value: resource.id, label: `${resource.venue} — ${resource.name} (${resource.sport})` }))"
                                    class="mt-2"
                                    aria-label="Court to block"
                                />
                                <FormError :message="form.errors.resource_id" />
                            </label>

                            <label>
                                <span class="text-sm font-medium text-slate-700">Date</span>
                                <input v-model="form.block_date" type="date" :min="defaultDate" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                                <FormError :message="form.errors.block_date" />
                            </label>

                            <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 sm:self-end">
                                <input v-model="form.is_all_day" type="checkbox" class="size-4 rounded border-slate-300 text-court-700 focus:ring-court-500">
                                <span><strong class="block text-sm font-semibold text-slate-800">Block the whole day</strong><span class="mt-0.5 block text-xs text-slate-500">All opening hours become unavailable.</span></span>
                            </label>

                            <template v-if="!form.is_all_day">
                                <label>
                                    <span class="text-sm font-medium text-slate-700">Start time</span>
                                    <input v-model="form.start_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                                </label>
                                <label>
                                    <span class="text-sm font-medium text-slate-700">End time</span>
                                    <input v-model="form.end_time" type="time" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                                </label>
                            </template>
                            <div class="sm:col-span-2"><FormError :message="form.errors.start_time || form.errors.end_time" /></div>

                            <label>
                                <span class="text-sm font-medium text-slate-700">Repeat</span>
                                <AppSelect v-model="form.repeat" :options="[{ value: 'none', label: 'Does not repeat' }, { value: 'daily', label: 'Every day' }, { value: 'weekly', label: 'Every week' }]" class="mt-2" aria-label="Repeat court block" />
                                <FormError :message="form.errors.repeat" />
                            </label>
                            <label v-if="form.repeat !== 'none'">
                                <span class="text-sm font-medium text-slate-700">Repeat until</span>
                                <input v-model="form.repeat_until" type="date" :min="form.block_date" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                                <FormError :message="form.errors.repeat_until" />
                            </label>
                        </div>
                    </section>

                    <section class="app-card p-6 sm:p-7">
                        <h3 class="text-lg font-semibold text-slate-950">Why is this court blocked?</h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">A reason is required so you and your staff understand the schedule later. Players only see that the time is unavailable.</p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button v-for="reason in suggestions" :key="reason" type="button" class="rounded-full border border-court-200 bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800 hover:border-court-400" @click="useReason(reason)">{{ reason }}</button>
                        </div>

                        <label class="mt-5 block">
                            <span class="text-sm font-medium text-slate-700">Reason</span>
                            <textarea v-model="form.reason" rows="4" maxlength="500" placeholder="Example: Resurfacing work; reopen after the morning inspection." class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5" />
                            <span class="mt-1.5 block text-right text-xs text-slate-400">{{ form.reason.length }}/500</span>
                            <FormError :message="form.errors.reason" />
                        </label>
                    </section>
                </div>

                <aside class="space-y-5 lg:sticky lg:top-6 lg:self-start">
                    <section class="rounded-2xl bg-court-950 p-5 text-white">
                        <p class="text-xs font-semibold uppercase tracking-wider text-court-300">What happens next</p>
                        <ul class="mt-4 space-y-3 text-sm leading-6 text-court-50">
                            <li>• The selected time immediately becomes unavailable.</li>
                            <li>• Existing reservations are never cancelled.</li>
                            <li>• Optional daily or weekly blocks are created together.</li>
                            <li>• You can reopen each time from Bookings.</li>
                        </ul>
                    </section>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Blocking…' : 'Block this court time' }}</button>
                    <Link href="/owner/bookings" class="block text-center text-sm font-semibold text-slate-500">Cancel</Link>
                </aside>
            </form>
        </div>
    </OwnerLayout>
</template>
