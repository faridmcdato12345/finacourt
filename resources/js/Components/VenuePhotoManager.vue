<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormError from './FormError.vue';

const props = defineProps({
    venue: Object,
});

const picker = ref(null);
const upload = useForm({ photos: [] });
const photoErrors = computed(() => Object.entries(upload.errors).filter(([key]) => key.startsWith('photos.')));

function selectPhotos(event) {
    upload.photos = Array.from(event.target.files || []);
}

function uploadPhotos() {
    upload.post(`/owner/venues/${props.venue.id}/photos`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            upload.reset();
            if (picker.value) picker.value.value = '';
        },
    });
}

function makePrimary(photo) {
    router.patch(`/owner/venues/${props.venue.id}/photos/${photo.id}`, { is_primary: true }, { preserveScroll: true });
}

function deletePhoto(photo) {
    if (window.confirm('Delete this venue photo?')) {
        router.delete(`/owner/venues/${props.venue.id}/photos/${photo.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Venue photos</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Add up to 10 JPG, PNG, or WebP photos. The cover photo appears first on the marketplace.</p>
            </div>
            <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ venue.photos.length }}/10</span>
        </div>

        <div v-if="venue.photos.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article v-for="photo in venue.photos" :key="photo.id" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
                    <img :src="photo.url" :alt="photo.alt_text || `${venue.name} venue photo`" class="size-full object-cover" loading="lazy" />
                    <span v-if="photo.is_primary" class="absolute left-3 top-3 rounded-lg bg-court-800 px-2.5 py-1 text-xs font-semibold text-white shadow">Cover photo</span>
                </div>
                <div class="flex items-center justify-between gap-2 p-3">
                    <button v-if="!photo.is_primary" type="button" class="text-xs font-semibold text-court-700 hover:text-court-900" @click="makePrimary(photo)">Make cover</button>
                    <span v-else class="text-xs font-medium text-slate-500">Shown first publicly</span>
                    <button type="button" class="text-xs font-semibold text-red-600 hover:text-red-800" @click="deletePhoto(photo)">Delete</button>
                </div>
            </article>
        </div>
        <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
            <p class="text-sm font-medium text-slate-700">No venue photos yet</p>
            <p class="mt-1 text-xs text-slate-500">Upload a clear exterior or court photo to replace the public placeholder.</p>
        </div>

        <form class="mt-6 rounded-2xl bg-court-50 p-4" @submit.prevent="uploadPhotos">
            <label for="venue-photos" class="block text-sm font-semibold text-court-950">Choose photos</label>
            <input
                id="venue-photos"
                ref="picker"
                type="file"
                name="photos[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                required
                class="mt-3 block w-full text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-white file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-court-800 file:shadow-sm hover:file:bg-court-100"
                @change="selectPhotos"
            />
            <p class="mt-2 text-xs text-slate-500">Up to 5 photos per upload and 5 MB per photo.</p>
            <FormError :message="upload.errors.photos" />
            <FormError v-for="([key, message]) in photoErrors" :key="key" :message="message" />
            <button
                type="submit"
                :disabled="upload.processing || !upload.photos.length || venue.photos.length >= 10"
                class="mt-4 rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-court-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ upload.processing ? 'Uploading…' : 'Upload photos' }}
            </button>
            <progress v-if="upload.progress" class="mt-3 h-2 w-full accent-court-700" :value="upload.progress.percentage" max="100">{{ upload.progress.percentage }}%</progress>
        </form>
    </section>
</template>
