<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import VenueForm from '../../../Components/VenueForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ sports: Array, amenities: Array, locationParents: Array, mapEmbedBaseUrl: String });

const form = useForm({
    name: '', slug: '', description: '', address: '', city: '', province: '',
    psgc_parent_code: '', psgc_city_municipality_code: '',
    latitude: '', longitude: '', phone: '', email: '', website: '',
    is_published: false, sports: [], amenities: [], photos: [],
});

function submit() {
    form.post('/owner/venues', { forceFormData: true });
}
</script>

<template>
    <Head title="Create venue" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <Link href="/owner/venues" class="text-sm font-semibold text-court-700 hover:text-court-800">← Back to venues</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Create a venue</h2>
            <p class="mt-2 text-slate-600">Add one physical facility for the current organization.</p>
            <div class="mt-8">
                <VenueForm :form="form" :sports="sports" :amenities="amenities" :location-parents="locationParents" :map-embed-base-url="mapEmbedBaseUrl" allow-photo-upload submit-label="Create venue" @submit="submit" />
            </div>
        </div>
    </OwnerLayout>
</template>
