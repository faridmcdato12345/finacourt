<script setup>
import AppSelect from './AppSelect.vue';
import FormError from './FormError.vue';

defineProps({
    form: Object,
    sports: Array,
    resourceTypes: Array,
    settings: Array,
    increments: Array,
    submitLabel: String,
});

defineEmits(['submit']);
</script>

<template>
    <form class="space-y-7" @submit.prevent="$emit('submit')">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-800">Court name</label>
                    <input id="name" v-model="form.name" required placeholder="Court 1" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.name" />
                </div>
                <div>
                    <label for="sport_id" class="mb-2 block text-sm font-medium text-slate-800">Sport</label>
                    <AppSelect id="sport_id" v-model="form.sport_id" :options="sports" option-value="id" option-label="name" placeholder="Select a sport" required aria-label="Sport" />
                    <FormError :message="form.errors.sport_id" />
                </div>
                <div>
                    <label for="resource_type" class="mb-2 block text-sm font-medium text-slate-800">Court type</label>
                    <AppSelect id="resource_type" v-model="form.resource_type" :options="resourceTypes" required aria-label="Court type" />
                    <FormError :message="form.errors.resource_type" />
                </div>
                <div>
                    <label for="setting" class="mb-2 block text-sm font-medium text-slate-800">Setting</label>
                    <AppSelect id="setting" v-model="form.setting" :options="settings" required aria-label="Court setting" />
                    <FormError :message="form.errors.setting" />
                </div>
                <div>
                    <label for="booking_increment_minutes" class="mb-2 block text-sm font-medium text-slate-800">Smallest booking time</label>
                    <AppSelect id="booking_increment_minutes" v-model="form.booking_increment_minutes" :options="increments.map((increment) => ({ value: increment, label: `${increment} minutes` }))" required aria-label="Smallest booking time" />
                    <FormError :message="form.errors.booking_increment_minutes" />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-950">Price and booking status</h2>
            <p class="mt-1 text-sm text-slate-500">This is the normal hourly price players will see.</p>
            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="base_hourly_rate" class="mb-2 block text-sm font-medium text-slate-800">Hourly rate (PHP)</label>
                    <div class="flex rounded-xl border border-slate-300 bg-white shadow-sm focus-within:border-court-600">
                        <span class="border-r border-slate-200 px-4 py-3 text-slate-500">₱</span>
                        <input id="base_hourly_rate" v-model="form.base_hourly_rate" type="number" min="0" max="999999.99" step="0.01" required class="min-w-0 flex-1 rounded-r-xl border-0 px-4 py-3 focus:outline-none" />
                    </div>
                    <FormError :message="form.errors.base_hourly_rate" />
                </div>
                <label class="flex items-center gap-4 self-end rounded-xl border border-slate-200 px-4 py-3">
                    <input v-model="form.is_active" type="checkbox" class="size-5 rounded border-slate-300 text-court-700" />
                    <span><span class="block text-sm font-semibold text-slate-900">Allow bookings</span><span class="text-xs text-slate-500">Turn this off when players should not be able to book this court.</span></span>
                </label>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:opacity-60">{{ form.processing ? 'Saving…' : submitLabel }}</button>
        </div>
    </form>
</template>
