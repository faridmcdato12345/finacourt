<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ResourceForm from '../../../Components/ResourceForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object, sports: Array, resourceTypes: Array, settings: Array, increments: Array });
const form = useForm({
    name: '', sport_id: '', resource_type: 'court', setting: 'indoor',
    is_active: true, base_hourly_rate: '', booking_increment_minutes: 60,
});

function submit() {
    form.post(`/owner/venues/${props.venue.id}/resources`);
}
</script>

<template>
    <Head title="Add resource" />
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <Link :href="`/owner/venues/${venue.id}`" class="text-sm font-semibold text-court-700">← Back to {{ venue.name }}</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Add a court or resource</h2>
            <p class="mt-2 text-slate-600">Create a generic booking target with its sport, setting, status, and base price.</p>
            <div class="mt-8"><ResourceForm :form="form" :sports="sports" :resource-types="resourceTypes" :settings="settings" :increments="increments" submit-label="Create resource" @submit="submit" /></div>
        </div>
    </OwnerLayout>
</template>

