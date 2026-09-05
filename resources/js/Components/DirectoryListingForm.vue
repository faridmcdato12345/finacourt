<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppSelect from './AppSelect.vue';

const props = defineProps({
    listing: { type: Object, default: null },
    sports: Array,
    sourceTypes: Array,
    weekdays: Array,
    locationParents: { type: Array, default: () => [] },
});
const initialHours = props.weekdays.map((day) => {
    const existing = props.listing?.hours?.find((hour) => Number(hour.day_of_week) === Number(day.value));
    return existing || { day_of_week: day.value, is_closed: true, opens_at: '08:00', closes_at: '22:00' };
});
const form = useForm({
    name: props.listing?.name || '', description: props.listing?.description || '', address: props.listing?.address || '', city: props.listing?.city || '', province: props.listing?.province || '', country: props.listing?.country || 'Philippines',
    psgc_parent_code: props.listing?.psgc_parent_code || '', psgc_city_municipality_code: props.listing?.psgc_city_municipality_code || '',
    latitude: props.listing?.latitude || '', longitude: props.listing?.longitude || '', coordinates_verified: props.listing?.coordinates_verified || false,
    phone: props.listing?.phone || '', email: props.listing?.email || '', website: props.listing?.website || '',
    source_type: props.listing?.source_type || 'official_website', source_url: props.listing?.source_url || '', source_reference: props.listing?.source_reference || '',
    rights_confirmed: false, sports: props.listing?.sports || [], hours: initialHours,
});

const cityMunicipalities = ref([]);
const locationOptionsLoading = ref(false);
const locationOptionsError = ref('');
const usesPsgcCatalog = computed(() => props.locationParents.length > 0
    && String(form.country || '').trim().toLowerCase() === 'philippines');
let locationRequest = 0;

watch(
    () => form.psgc_parent_code,
    async (parentCode, previousParentCode) => {
        if (previousParentCode !== undefined && parentCode !== previousParentCode) {
            form.psgc_city_municipality_code = '';
        }

        cityMunicipalities.value = [];
        locationOptionsError.value = '';

        if (!usesPsgcCatalog.value || !parentCode) return;

        const requestNumber = ++locationRequest;
        locationOptionsLoading.value = true;

        try {
            const response = await fetch(`/platform/location-options/cities?parent_code=${encodeURIComponent(parentCode)}`, {
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

watch(usesPsgcCatalog, (enabled, wasEnabled) => {
    if (!enabled && wasEnabled) {
        form.psgc_parent_code = '';
        form.psgc_city_municipality_code = '';
        cityMunicipalities.value = [];
    }
});

function submit() {
    if (props.listing) form.put(`/platform/directory/${props.listing.slug}`);
    else form.post('/platform/directory');
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section class="app-card grid gap-5 p-6 sm:grid-cols-2"><div class="sm:col-span-2"><h2 class="text-lg font-semibold">Venue details</h2><p class="mt-1 text-sm text-slate-500">Use your own wording and include only details that anyone can check publicly. Leave out reviews, photos, prices, and availability until the owner joins.</p></div>
            <label class="text-sm font-semibold sm:col-span-2">Venue name<input v-model="form.name" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span></label>
            <label class="text-sm font-semibold sm:col-span-2">Short description <span class="text-slate-400">(optional)</span><textarea v-model="form.description" maxlength="3000" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="Write a short factual description in your own words."></textarea></label>
            <label class="text-sm font-semibold sm:col-span-2">Address<input v-model="form.address" required autocomplete="street-address" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.address" class="mt-1 block text-xs text-red-600">{{ form.errors.address }}</span></label>
            <label class="text-sm font-semibold">Country<input v-model="form.country" required autocomplete="country-name" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.country" class="mt-1 block text-xs text-red-600">{{ form.errors.country }}</span></label><div></div>
            <template v-if="usesPsgcCatalog">
                <div>
                    <label for="directory_psgc_parent_code" class="text-sm font-semibold">Province / region</label>
                    <AppSelect id="directory_psgc_parent_code" v-model="form.psgc_parent_code" :options="locationParents" option-value="code" option-label="label" placeholder="Select a province or region" required autocomplete="address-level1" class="mt-2" />
                    <span v-if="form.errors.psgc_parent_code" class="mt-1 block text-xs text-red-600">{{ form.errors.psgc_parent_code }}</span>
                </div>
                <div>
                    <label for="directory_psgc_city_code" class="text-sm font-semibold">City / municipality</label>
                    <AppSelect id="directory_psgc_city_code" v-model="form.psgc_city_municipality_code" :options="cityMunicipalities" option-value="code" option-label="name" :placeholder="locationOptionsLoading ? 'Loading locations…' : form.psgc_parent_code ? 'Select a city or municipality' : 'Select a province or region first'" required autocomplete="address-level2" :disabled="!form.psgc_parent_code || locationOptionsLoading" class="mt-2" />
                    <span v-if="form.errors.psgc_city_municipality_code" class="mt-1 block text-xs text-red-600">{{ form.errors.psgc_city_municipality_code }}</span>
                    <p v-if="locationOptionsError" class="mt-2 text-xs text-red-600" role="alert">{{ locationOptionsError }}</p>
                </div>
                <p class="rounded-xl bg-court-50 px-4 py-3 text-xs leading-5 text-court-900 sm:col-span-2">Philippine locations come from FinACourt’s bundled PSA PSGC catalog. We save the official city and province names after checking their hierarchy.</p>
            </template>
            <template v-else>
                <label class="text-sm font-semibold">Province / region<input v-model="form.province" required autocomplete="address-level1" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.province" class="mt-1 block text-xs text-red-600">{{ form.errors.province }}</span></label>
                <label class="text-sm font-semibold">City<input v-model="form.city" required autocomplete="address-level2" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.city" class="mt-1 block text-xs text-red-600">{{ form.errors.city }}</span></label>
            </template>
            <label class="text-sm font-semibold">Latitude <span class="text-slate-400">(optional)</span><input v-model="form.latitude" type="number" step="any" min="-90" max="90" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.latitude" class="mt-1 block text-xs text-red-600">{{ form.errors.latitude }}</span></label><label class="text-sm font-semibold">Longitude <span class="text-slate-400">(optional)</span><input v-model="form.longitude" type="number" step="any" min="-180" max="180" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"><span v-if="form.errors.longitude" class="mt-1 block text-xs text-red-600">{{ form.errors.longitude }}</span></label>
            <label class="flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:col-span-2"><input v-model="form.coordinates_verified" type="checkbox" class="mt-1 size-4 accent-court-700"><span><strong class="block">I checked that this map pin points to the venue</strong><span class="mt-1 block text-slate-500">If it is not checked, we will ask the owner to confirm the location later.</span></span></label>
            <label class="text-sm font-semibold">Public phone<input v-model="form.phone" maxlength="40" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"></label><label class="text-sm font-semibold">Public email<input v-model="form.email" type="email" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"></label><label class="text-sm font-semibold sm:col-span-2">Public website<input v-model="form.website" type="url" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"></label>
        </section>

        <section class="app-card p-6"><h2 class="text-lg font-semibold">Sports offered</h2><div class="mt-4 grid gap-3 sm:grid-cols-3"><label v-for="sport in sports" :key="sport.id" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm"><input v-model="form.sports" type="checkbox" :value="sport.id" class="size-4 accent-court-700">{{ sport.name }}</label></div><span v-if="form.errors.sports" class="mt-2 block text-xs text-red-600">{{ form.errors.sports }}</span></section>

        <section class="app-card p-6"><h2 class="text-lg font-semibold">Opening hours</h2><p class="mt-1 text-sm text-slate-500">Only add hours you found in a trustworthy public source. Players will still be asked to confirm them with the venue.</p><div class="mt-5 space-y-3"><div v-for="(hour, index) in form.hours" :key="hour.day_of_week" class="grid items-center gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-[130px_110px_1fr_1fr]"><span class="text-sm font-semibold">{{ weekdays.find((day) => Number(day.value) === Number(hour.day_of_week))?.label }}</span><label class="flex items-center gap-2 text-sm"><input v-model="hour.is_closed" type="checkbox" class="size-4 accent-court-700">Closed</label><input v-model="hour.opens_at" :disabled="hour.is_closed" type="time" class="rounded-lg border border-slate-200 px-3 py-2 disabled:bg-slate-100"><input v-model="hour.closes_at" :disabled="hour.is_closed" type="time" class="rounded-lg border border-slate-200 px-3 py-2 disabled:bg-slate-100"><span v-if="form.errors[`hours.${index}.closes_at`]" class="text-xs text-red-600 sm:col-span-4">{{ form.errors[`hours.${index}.closes_at`] }}</span></div></div></section>

        <section class="app-card grid gap-5 p-6 sm:grid-cols-2"><div class="sm:col-span-2"><h2 class="text-lg font-semibold">Where did the information come from?</h2><p class="mt-1 text-sm text-slate-500">Add enough detail for another team member to check it. We keep a private record of changes, but only the public source type and link appear on the venue page.</p></div><label class="text-sm font-semibold">Type of source<AppSelect v-model="form.source_type" :options="sourceTypes" class="mt-2" /></label><div></div><label class="text-sm font-semibold sm:col-span-2">Public source link <span class="text-slate-400">(or add a note below)</span><input v-model="form.source_url" type="url" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal"></label><label class="text-sm font-semibold sm:col-span-2">Private source note <span class="text-slate-400">(only the FinACourt team can see this)</span><input v-model="form.source_reference" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="For example: checked during a venue visit or against a registry record"></label>
            <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950 sm:col-span-2"><input v-model="form.rights_confirmed" required type="checkbox" class="mt-1 size-4 accent-court-700"><span>I checked that FinACourt can publish these basic facts and this original description. I did not copy a competitor's venue page, reviews, photos, or copyrighted description.</span></label><span v-if="form.errors.rights_confirmed" class="text-xs text-red-600 sm:col-span-2">{{ form.errors.rights_confirmed }}</span>
        </section>
        <div v-if="form.errors.listing" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ form.errors.listing }}</div>
        <button :disabled="form.processing" class="rounded-xl bg-court-700 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ listing ? 'Save changes' : 'Save as draft' }}</button>
    </form>
</template>
