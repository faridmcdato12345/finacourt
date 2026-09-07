<script setup>
import { computed, ref, watch } from 'vue';
import SearchableSelect from './SearchableSelect.vue';
import DraggableVenueMap from './DraggableVenueMap.vue';
import FormError from './FormError.vue';
import { detectCurrentCoordinates, locationErrorMessage } from '../lib/geolocation';
import { normalizeMapCoordinates } from '../lib/venue-map';

const props = defineProps({
    form: Object,
    sports: Array,
    amenities: Array,
    locationParents: { type: Array, default: () => [] },
    submitLabel: String,
    existingState: Object,
    mapTileUrl: String,
    allowPhotoUpload: { type: Boolean, default: false },
    applicationMode: { type: Boolean, default: false },
});

defineEmits(['submit']);

const hasMapCoordinates = computed(() => normalizeMapCoordinates(props.form.latitude, props.form.longitude) !== null);

const photoErrors = computed(() => Object.entries(props.form.errors).filter(([key]) => key.startsWith('photos.')));
const cityMunicipalities = ref([]);
const locationOptionsLoading = ref(false);
const locationOptionsError = ref('');
const detectingLocation = ref(false);
const detectedLocationMessage = ref('');
const detectedLocationError = ref('');
let locationRequest = 0;

const cityOptionsKey = computed(() => [
    props.form.psgc_parent_code || 'no-parent',
    cityMunicipalities.value.map((location) => location.code).join(','),
].join(':'));

const needsMarketplaceReview = computed(() => (
    props.existingState?.requires_platform_review
    && !props.existingState?.is_verified
));

const isMarketplaceReviewRequested = computed(() => (
    needsMarketplaceReview.value
    && Boolean(props.existingState?.marketplace_review_requested_at)
));

const visibilityActionLabel = computed(() => {
    if (!props.form.is_published) return 'Action needed: turn this on before saving';
    if (needsMarketplaceReview.value && !isMarketplaceReviewRequested.value) return 'Ready to request the final FinACourt check';
    if (isMarketplaceReviewRequested.value) return 'Final FinACourt check requested';

    return 'Ready to show once a bookable court is added';
});

const effectiveSubmitLabel = computed(() => {
    if (props.applicationMode || !props.form.is_published) return props.submitLabel;
    if (needsMarketplaceReview.value && !isMarketplaceReviewRequested.value) return 'Save and request final check';

    return props.submitLabel;
});

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

async function useCurrentLocation() {
    detectingLocation.value = true;
    detectedLocationMessage.value = '';
    detectedLocationError.value = '';

    try {
        const coordinates = await detectCurrentCoordinates(
            typeof navigator === 'undefined' ? null : navigator.geolocation,
        );

        props.form.latitude = coordinates.latitude;
        props.form.longitude = coordinates.longitude;
        detectedLocationMessage.value = coordinates.accuracy === null
            ? 'Location found. Check that the pin points to the venue entrance.'
            : `Location found within about ${coordinates.accuracy} metres. Check that the pin points to the venue entrance.`;
    } catch (error) {
        detectedLocationError.value = locationErrorMessage(error);
    } finally {
        detectingLocation.value = false;
    }
}

function updateCoordinatesFromMap(coordinates) {
    props.form.latitude = coordinates.latitude;
    props.form.longitude = coordinates.longitude;
    detectedLocationError.value = '';
    detectedLocationMessage.value = coordinates.action === 'drag'
        ? 'Pin moved. These map numbers now point to the new position.'
        : 'Pin placed. These map numbers now point to the spot you selected.';
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
                    <SearchableSelect id="psgc_parent_code" v-model="form.psgc_parent_code" :options="locationParents" option-value="code" option-label="label" placeholder="Search for a province or region" search-label="Province or region" empty-label="No matching province or region found." required autocomplete="address-level1" aria-label="Search for a province or region" />
                    <FormError :message="form.errors.psgc_parent_code" />
                </div>
                <div>
                    <label for="psgc_city_municipality_code" class="mb-2 block text-sm font-medium text-slate-800">City / municipality</label>
                    <SearchableSelect :key="cityOptionsKey" id="psgc_city_municipality_code" v-model="form.psgc_city_municipality_code" :options="cityMunicipalities" option-value="code" option-label="name" :placeholder="locationOptionsLoading ? 'Loading locations…' : form.psgc_parent_code ? 'Search for a city or municipality' : 'Select a province or region first'" search-label="City or municipality" empty-label="No matching city or municipality found." required autocomplete="address-level2" :disabled="!form.psgc_parent_code || locationOptionsLoading" aria-label="Search for a city or municipality" />
                    <FormError :message="form.errors.psgc_city_municipality_code" />
                    <p v-if="locationOptionsError" class="mt-2 text-sm text-red-600" role="alert">{{ locationOptionsError }}</p>
                </div>
                <div class="sm:col-span-2 rounded-2xl border border-court-100 bg-court-50 p-4">
                    <div class="sm:flex sm:items-center sm:justify-between sm:gap-5">
                        <div>
                            <p class="font-semibold text-court-950">At the venue right now?</p>
                            <p class="mt-1 text-sm leading-6 text-court-800">Let your phone or computer place the map pin for you. Your browser will ask for permission first.</p>
                        </div>
                        <button
                            type="button"
                            :disabled="detectingLocation"
                            class="mt-4 inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-court-800 disabled:cursor-wait disabled:opacity-70 sm:mt-0"
                            @click="useCurrentLocation"
                        >
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="12" cy="12" r="3" />
                                <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
                            </svg>
                            {{ detectingLocation ? 'Finding your location…' : 'Use my current location' }}
                        </button>
                    </div>
                    <p v-if="detectedLocationMessage" class="mt-3 text-sm font-medium text-court-800" role="status">{{ detectedLocationMessage }}</p>
                    <p v-if="detectedLocationError" class="mt-3 text-sm font-medium text-red-700" role="alert">{{ detectedLocationError }}</p>
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
                    <p class="mb-3 text-sm font-medium leading-6 text-slate-600">Drag the green pin to the venue entrance, or click the exact spot on the map. The map numbers update automatically.</p>
                    <div v-if="hasMapCoordinates" class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-sm">
                        <DraggableVenueMap
                            :latitude="form.latitude"
                            :longitude="form.longitude"
                            :tile-url="mapTileUrl"
                            @change="updateCoordinatesFromMap"
                        />
                    </div>
                    <p v-else class="rounded-xl bg-slate-50 px-4 py-5 text-sm text-slate-500">Use your current location or enter both map numbers to show the movable pin.</p>
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

        <section
            v-if="!applicationMode"
            :class="[
                'relative overflow-hidden rounded-3xl border-2 p-6 shadow-sm ring-4 transition sm:p-7',
                form.is_published
                    ? 'border-court-300 bg-court-50 ring-court-100/70'
                    : 'border-amber-300 bg-amber-50 ring-amber-100/80',
            ]"
        >
            <div aria-hidden="true" :class="['absolute -right-10 -top-10 size-36 rounded-full border-[24px]', form.is_published ? 'border-court-200/50' : 'border-amber-200/60']"></div>
            <div class="relative">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-4">
                        <div :class="['grid size-12 shrink-0 place-items-center rounded-2xl shadow-sm', form.is_published ? 'bg-court-700 text-white' : 'bg-amber-400 text-amber-950']">
                            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
                                <circle cx="12" cy="12" r="2.5" />
                            </svg>
                        </div>
                        <div>
                            <p :class="['text-xs font-bold uppercase tracking-[0.18em]', form.is_published ? 'text-court-700' : 'text-amber-800']">{{ form.onboarding ? 'Final onboarding step' : 'Marketplace visibility' }}</p>
                            <h2 class="mt-1 text-xl font-semibold text-slate-950">Make this venue discoverable</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Players cannot find or book this venue until you choose to show it and complete any required FinACourt check.</p>
                        </div>
                    </div>
                    <span :class="['inline-flex rounded-full px-3 py-1.5 text-xs font-bold', form.is_published ? 'bg-court-700 text-white' : 'bg-amber-200 text-amber-950']">{{ visibilityActionLabel }}</span>
                </div>

                <label
                    for="is_published"
                    :class="[
                        'mt-6 flex cursor-pointer items-start gap-4 rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md',
                        form.is_published ? 'border-court-300' : 'border-amber-300',
                    ]"
                >
                    <input id="is_published" v-model="form.is_published" type="checkbox" class="mt-0.5 size-6 shrink-0 rounded border-slate-300 text-court-700 focus:ring-court-500" />
                    <span>
                        <span class="block text-base font-bold text-slate-950">Show this venue to players</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-600">{{ needsMarketplaceReview ? 'Turn this on and save to ask FinACourt for the final marketplace check. We will email you when players can find and book the venue.' : 'Players can find this venue after it has at least one sport and one active court they can book.' }}</span>
                    </span>
                </label>
                <FormError :message="form.errors.is_published" />

                <div v-if="existingState" class="mt-5 flex flex-wrap gap-2 border-t border-slate-900/10 pt-5 text-xs font-semibold">
                    <span class="rounded-full bg-white px-3 py-1.5 text-court-800 shadow-sm">{{ existingState.is_claimed ? 'Claimed from the public guide' : 'Created in your account' }}</span>
                    <span v-if="existingState.is_verified" class="rounded-full bg-white px-3 py-1.5 text-court-800 shadow-sm">Final check completed</span>
                    <span v-else-if="existingState.requires_platform_review" class="rounded-full bg-white px-3 py-1.5 text-slate-700 shadow-sm">{{ existingState.marketplace_review_requested_at ? 'Final check requested' : 'Final check not requested' }}</span>
                </div>
            </div>
        </section>

        <section v-else class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm leading-6 text-amber-950 shadow-sm">
            <h2 class="font-semibold">This application stays private</h2>
            <p class="mt-1">Submitting these details starts FinACourt’s independent ownership review. After approval, you can add courts and request a separate final marketplace review.</p>
        </section>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:opacity-60">{{ form.processing ? 'Saving…' : effectiveSubmitLabel }}</button>
        </div>
    </form>
</template>
