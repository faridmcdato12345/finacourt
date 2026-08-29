<script setup>
import { computed, ref, watch } from 'vue';
import AppSelect from './AppSelect.vue';
import FormError from './FormError.vue';

const props = defineProps({
    form: Object,
    sports: Array,
    amenities: Array,
    locationParents: { type: Array, default: () => [] },
    submitLabel: String,
    existingState: Object,
    mapEmbedBaseUrl: String,
    allowPhotoUpload: { type: Boolean, default: false },
});

defineEmits(['submit']);

const mapPreviewUrl = computed(() => {
    const latitude = Number(props.form.latitude);
    const longitude = Number(props.form.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || props.form.latitude === '' || props.form.longitude === '') return null;

    const bbox = [longitude - 0.009, latitude - 0.006, longitude + 0.009, latitude + 0.006].join(',');
    const query = new URLSearchParams({ bbox, layer: 'mapnik', marker: `${latitude},${longitude}` });
    return `${props.mapEmbedBaseUrl}?${query.toString()}`;
});

const photoErrors = computed(() => Object.entries(props.form.errors).filter(([key]) => key.startsWith('photos.')));
const cityMunicipalities = ref([]);
const locationOptionsLoading = ref(false);
const locationOptionsError = ref('');
let locationRequest = 0;

watch(
    () => props.form.psgc_parent_code,
    async (parentCode, previousParentCode) => {
        if (previousParentCode !== undefined && parentCode !== previousParentCode) {
            props.form.psgc_city_municipality_code = '';
        }

        cityMunicipalities.value = [];
        locationOptionsError.value = '';

        if (!parentCode) return;

        const requestNumber = ++locationRequest;
        locationOptionsLoading.value = true;

        try {
            const response = await fetch(`/owner/location-options/cities?parent_code=${encodeURIComponent(parentCode)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) throw new Error('Unable to load cities and municipalities.');
            const payload = await response.json();

            if (requestNumber === locationRequest) cityMunicipalities.value = payload.data || [];
        } catch (error) {
            if (requestNumber === locationRequest) {
                locationOptionsError.value = 'Cities and municipalities could not be loaded. Check your connection and try again.';
            }
        } finally {
            if (requestNumber === locationRequest) locationOptionsLoading.value = false;
        }
    },
    { immediate: true },
);

function selectPhotos(event) {
    props.form.photos = Array.from(event.target.files || []);
}
</script>

<template>
    <form class="space-y-7" @submit.prevent="$emit('submit')">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-950">Basic details</h2>
                <p class="mt-1 text-sm text-slate-500">The name and description players will see.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-800">Venue name</label>
                    <input id="name" v-model="form.name" required class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.name" />
                </div>
                <div>
                    <label for="slug" class="mb-2 block text-sm font-medium text-slate-800">Page address <span class="font-normal text-slate-400">(we can fill this in)</span></label>
                    <input id="slug" v-model="form.slug" placeholder="venue-name" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.slug" />
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-800">Description</label>
                    <textarea id="description" v-model="form.description" rows="5" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" placeholder="Describe the facility, atmosphere, and what players should know."></textarea>
                    <FormError :message="form.errors.description" />
                </div>
            </div>
        </section>

        <section v-if="allowPhotoUpload" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Venue photos</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Add initial photos now. The first selected image becomes the cover photo players see.</p>
            </div>
            <label for="initial-venue-photos" class="mt-6 block text-sm font-semibold text-slate-800">Choose photos <span class="font-normal text-slate-400">(optional)</span></label>
            <input
                id="initial-venue-photos"
                type="file"
                name="photos[]"
                accept="image/jpeg,image/png,image/webp"
                multiple
                class="mt-3 block w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-white file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-court-800 file:shadow-sm hover:file:bg-court-100"
                @change="selectPhotos"
            />
            <p class="mt-2 text-xs text-slate-500">Choose up to 5 JPG, PNG, or WebP photos, with a 5 MB limit per file.</p>
            <ul v-if="form.photos?.length" class="mt-4 space-y-2 text-sm text-slate-600">
                <li v-for="(photo, index) in form.photos" :key="`${photo.name}-${photo.size}-${index}`" class="flex items-center justify-between gap-3 rounded-xl bg-court-50 px-4 py-2.5">
                    <span class="truncate">{{ photo.name }}</span>
                    <span class="shrink-0 text-xs font-medium text-slate-400">{{ index === 0 ? 'Cover' : `${(photo.size / 1024 / 1024).toFixed(1)} MB` }}</span>
                </li>
            </ul>
            <FormError :message="form.errors.photos" />
            <FormError v-for="([key, message]) in photoErrors" :key="key" :message="message" />
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-950">Location</h2>
                <p class="mt-1 text-sm text-slate-500">Players use city and province to find your venue.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="address" class="mb-2 block text-sm font-medium text-slate-800">Street address</label>
                    <input id="address" v-model="form.address" required autocomplete="street-address" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.address" />
                </div>
                <div>
                    <label for="psgc_parent_code" class="mb-2 block text-sm font-medium text-slate-800">Province / region</label>
                    <AppSelect id="psgc_parent_code" v-model="form.psgc_parent_code" :options="locationParents" option-value="code" option-label="label" placeholder="Select a province or region" required autocomplete="address-level1" aria-label="Province or region" />
                    <FormError :message="form.errors.psgc_parent_code" />
                </div>
                <div>
                    <label for="psgc_city_municipality_code" class="mb-2 block text-sm font-medium text-slate-800">City / municipality</label>
                    <AppSelect id="psgc_city_municipality_code" v-model="form.psgc_city_municipality_code" :options="cityMunicipalities" option-value="code" option-label="name" :placeholder="locationOptionsLoading ? 'Loading locations…' : form.psgc_parent_code ? 'Select a city or municipality' : 'Select a province or region first'" required autocomplete="address-level2" :disabled="!form.psgc_parent_code || locationOptionsLoading" aria-label="City or municipality" />
                    <FormError :message="form.errors.psgc_city_municipality_code" />
                    <p v-if="locationOptionsError" class="mt-2 text-sm text-red-600" role="alert">{{ locationOptionsError }}</p>
                </div>
                <div>
                    <label for="latitude" class="mb-2 block text-sm font-medium text-slate-800">Latitude <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="latitude" v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.latitude" />
                </div>
                <div>
                    <label for="longitude" class="mb-2 block text-sm font-medium text-slate-800">Longitude <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="longitude" v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.longitude" />
                </div>
                <div class="sm:col-span-2">
                    <p class="mb-3 text-xs leading-5 text-slate-500">Enter both map numbers when you know them, then check that the pin points to the venue entrance.</p>
                    <div v-if="mapPreviewUrl" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                        <iframe :src="mapPreviewUrl" title="Venue map pin preview" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" class="h-72 w-full border-0"></iframe>
                        <p class="border-t border-slate-200 px-4 py-2 text-[11px] text-slate-500">Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="font-semibold text-court-700">OpenStreetMap contributors</a></p>
                    </div>
                    <p v-else class="rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">The map preview appears after both map numbers are entered.</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-slate-950">Contact details</h2>
                <p class="mt-1 text-sm text-slate-500">Information players may eventually see on the public venue page.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="phone" class="mb-2 block text-sm font-medium text-slate-800">Phone</label>
                    <input id="phone" v-model="form.phone" type="tel" autocomplete="tel" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.phone" />
                </div>
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-800">Contact email</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.email" />
                </div>
                <div class="sm:col-span-2">
                    <label for="website" class="mb-2 block text-sm font-medium text-slate-800">Website</label>
                    <input id="website" v-model="form.website" type="url" placeholder="https://" class="w-full rounded-xl border border-slate-300 px-4 py-3 shadow-sm focus:border-court-600" />
                    <FormError :message="form.errors.website" />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-8 lg:grid-cols-2">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Sports offered</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose at least one sport. Your courts can only use sports you choose here.</p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <label v-for="sport in sports" :key="sport.id" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input v-model="form.sports" type="checkbox" :value="sport.id" class="size-4 rounded border-slate-300 text-court-700" />
                            {{ sport.name }}
                        </label>
                    </div>
                    <FormError :message="form.errors.sports" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Facilities and extras</h2>
                    <p class="mt-1 text-sm text-slate-500">Choose what players can use at this venue.</p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <label v-for="amenity in amenities" :key="amenity.id" class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <input v-model="form.amenities" type="checkbox" :value="amenity.id" class="size-4 rounded border-slate-300 text-court-700" />
                            {{ amenity.name }}
                        </label>
                    </div>
                    <FormError :message="form.errors.amenities" />
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-4">
                <input id="is_published" v-model="form.is_published" type="checkbox" class="mt-1 size-5 rounded border-slate-300 text-court-700" />
                <div>
                    <label for="is_published" class="font-semibold text-slate-950">Show this venue to players</label>
                    <p class="mt-1 text-sm leading-6 text-slate-500">{{ existingState?.requires_platform_review && !existingState?.is_verified ? 'This tells FinACourt that the venue is ready to be shown. Because it was claimed from the public guide, FinACourt must check it before players can find or book it.' : 'Players can find this venue after it has at least one sport and one court they can book.' }}</p>
                    <FormError :message="form.errors.is_published" />
                </div>
            </div>
            <div v-if="existingState" class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5 text-xs font-semibold">
                <span class="rounded-full bg-court-50 px-3 py-1.5 text-court-800">{{ existingState.is_claimed ? 'Claimed from the public guide' : 'Created in your account' }}</span>
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-600">{{ existingState.is_verified ? 'Checked by FinACourt' : 'Waiting for FinACourt check' }}</span>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:opacity-60">{{ form.processing ? 'Saving…' : submitLabel }}</button>
        </div>
    </form>
</template>
