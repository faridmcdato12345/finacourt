<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import VenueForm from '../../../Components/VenueForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ sports: Array, amenities: Array, locationParents: Array, mapTileUrl: String, returnToOnboarding: Boolean });

const form = useForm({
    name: '', slug: '', description: '', address: '', city: '', province: '',
    psgc_parent_code: '', psgc_city_municipality_code: '',
    latitude: '', longitude: '', phone: '', email: '', website: '',
    is_published: false, sports: [], amenities: [], photos: [],
    onboarding: props.returnToOnboarding,
});

function submit() {
    form.post('/owner/venues', { forceFormData: true });
}
</script>

<template>
    <Head title="Create venue" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <Link :href="returnToOnboarding ? '/owner/onboarding/venue' : '/owner/venues'" class="text-sm font-semibold text-court-700 hover:text-court-800">← {{ returnToOnboarding ? 'Back to setup progress' : 'Back to venues' }}</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">{{ returnToOnboarding ? 'New venue application' : 'Create a venue' }}</h2>
            <p class="mt-2 text-slate-600">{{ returnToOnboarding ? 'Enter trustworthy venue details. FinACourt will review ownership before private court setup unlocks.' : 'Add one location for your courts.' }}</p>
            <div class="mt-8">
                <VenueForm :form="form" :sports="sports" :amenities="amenities" :location-parents="locationParents" :map-tile-url="mapTileUrl" allow-photo-upload :application-mode="returnToOnboarding" :submit-label="returnToOnboarding ? 'Submit venue application' : 'Create venue'" @submit="submit" />
            </div>
        </div>
    </OwnerLayout>
</template>
