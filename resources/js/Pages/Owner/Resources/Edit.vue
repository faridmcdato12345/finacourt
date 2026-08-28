<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ResourceForm from '../../../Components/ResourceForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object, resource: Object, sports: Array, resourceTypes: Array, settings: Array, increments: Array });
const form = useForm({
    name: props.resource.name,
    sport_id: props.resource.sport_id,
    resource_type: props.resource.resource_type,
    setting: props.resource.setting,
    is_active: props.resource.is_active,
    base_hourly_rate: props.resource.base_hourly_rate,
    booking_increment_minutes: props.resource.booking_increment_minutes,
});

function submit() {
    form.put(`/owner/venues/${props.venue.id}/resources/${props.resource.id}`);
}
</script>

<template>
    <Head :title="`Edit ${resource.name}`" />
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <Link :href="`/owner/venues/${venue.id}`" class="text-sm font-semibold text-court-700">← Back to {{ venue.name }}</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Edit {{ resource.name }}</h2>
            <p class="mt-2 text-slate-600">Update whether this court can be booked, how long bookings can be, and the hourly price.</p>
            <div class="mt-8"><ResourceForm :form="form" :sports="sports" :resource-types="resourceTypes" :settings="settings" :increments="increments" submit-label="Save court" @submit="submit" /></div>
        </div>
    </OwnerLayout>
</template>
