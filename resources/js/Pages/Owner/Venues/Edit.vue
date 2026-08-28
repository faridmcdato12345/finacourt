<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import VenueForm from '../../../Components/VenueForm.vue';
import VenuePhotoManager from '../../../Components/VenuePhotoManager.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object, sports: Array, amenities: Array, locationParents: Array, mapEmbedBaseUrl: String });

const form = useForm({
    name: props.venue.name,
    slug: props.venue.slug,
    description: props.venue.description || '',
    address: props.venue.address,
    city: props.venue.city,
    province: props.venue.province,
    psgc_parent_code: props.venue.psgc_parent_code || '',
    psgc_city_municipality_code: props.venue.psgc_city_municipality_code || '',
    latitude: props.venue.latitude || '',
    longitude: props.venue.longitude || '',
    phone: props.venue.phone || '',
    email: props.venue.email || '',
    website: props.venue.website || '',
    is_published: props.venue.is_published,
    sports: props.venue.sports,
    amenities: props.venue.amenities,
});

function submit() {
    form.put(`/owner/venues/${props.venue.id}`);
}

function destroyVenue() {
    if (window.confirm(`Delete ${props.venue.name} and all of its resources? This cannot be undone.`)) {
        router.delete(`/owner/venues/${props.venue.id}`);
    }
}
</script>

<template>
    <Head :title="`Edit ${venue.name}`" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl">
            <Link :href="`/owner/venues/${venue.id}`" class="text-sm font-semibold text-court-700 hover:text-court-800">← Back to venue</Link>
            <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950">Edit {{ venue.name }}</h2>
            <p class="mt-2 text-slate-600">Update photos, location, sports, amenities, contact details, and publish state.</p>
            <div class="mt-8 space-y-7">
                <VenuePhotoManager :venue="venue" />
                <VenueForm :form="form" :sports="sports" :amenities="amenities" :location-parents="locationParents" :existing-state="venue" :map-embed-base-url="mapEmbedBaseUrl" submit-label="Save venue" @submit="submit" />
            </div>

            <section class="mt-10 rounded-2xl border border-red-200 bg-red-50 p-6">
                <h3 class="font-semibold text-red-900">Delete venue</h3>
                <p class="mt-1 text-sm leading-6 text-red-700">This also deletes its resources, operating hours, amenity links, and photo metadata.</p>
                <button type="button" class="mt-4 rounded-xl border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100" @click="destroyVenue">Delete venue</button>
            </section>
        </div>
    </OwnerLayout>
</template>
