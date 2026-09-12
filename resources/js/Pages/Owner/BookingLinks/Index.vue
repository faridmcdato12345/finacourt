<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    venues: Array,
    selectedVenue: Object,
    destination: Object,
    links: Array,
    performance: Object,
    filters: Object,
    sourceOptions: Array,
});

const copied = ref(null);
const destinationForm = useForm({
    provider_name: props.destination?.provider_name || '',
    destination_url: props.destination?.destination_url || '',
    is_active: props.destination?.is_active ?? true,
});
const customForm = useForm({ source: 'facebook', label: '', campaign: '' });
const defaultChannels = [
    { source: 'facebook', label: 'Facebook' },
    { source: 'google_maps', label: 'Google' },
    { source: 'instagram', label: 'Instagram' },
    { source: 'qr_code', label: 'QR Code' },
    { source: 'shared_link', label: 'Shared Link' },
];
const rangeOptions = [
    { value: 'this_week', label: 'This week' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_30_days', label: 'Last 30 days' },
];
const qrLinks = computed(() => props.links.filter((link) => link.source === 'qr_code'));

watch(() => [props.selectedVenue?.id, props.destination], () => {
    destinationForm.defaults({
        provider_name: props.destination?.provider_name || '',
        destination_url: props.destination?.destination_url || '',
        is_active: props.destination?.is_active ?? true,
    });
    destinationForm.reset();
    destinationForm.clearErrors();
});

function applyFilters(values = {}) {
    router.get('/owner/booking-links', { ...props.filters, ...values }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function saveDestination() {
    destinationForm.put(`/owner/venues/${props.selectedVenue.id}/external-booking-destination`, {
        preserveScroll: true,
    });
}

function defaultLink(source) {
    return props.links.find((link) => link.source === source && !link.campaign);
}

function createDefault(channel) {
    router.post(`/owner/venues/${props.selectedVenue.id}/booking-links`, {
        source: channel.source,
        label: channel.label,
    }, { preserveScroll: true });
}

function createCustom() {
    customForm.post(`/owner/venues/${props.selectedVenue.id}/booking-links`, {
        preserveScroll: true,
        onSuccess: () => customForm.reset('label', 'campaign'),
    });
}

async function copy(value, key) {
    if (!value || !navigator.clipboard) return;
    await navigator.clipboard.writeText(value);
    copied.value = key;
    window.setTimeout(() => { copied.value = null; }, 1800);
}

function rename(link) {
    const label = window.prompt('Booking link label', link.label);
    if (label?.trim()) router.patch(`/owner/booking-links/${link.id}`, { label: label.trim() }, { preserveScroll: true });
}

function toggle(link) {
    router.patch(`/owner/booking-links/${link.id}`, { is_active: !link.is_active }, { preserveScroll: true });
}

function remove(link) {
    if (window.confirm(`Delete ${link.label}? Its historical traffic totals will be kept.`)) {
        router.delete(`/owner/booking-links/${link.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Booking Links" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="eyebrow">Keep your current booking platform</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Booking Links</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Send players to your current external booking page through trackable FinACourt links, then see which channels sent the traffic.</p>
                </div>
                <div v-if="selectedVenue" class="app-card grid gap-3 p-3 sm:grid-cols-2">
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Venue<AppSelect :model-value="selectedVenue.id" :options="venues" option-value="id" option-label="name" size="sm" class="mt-1 min-w-48 normal-case tracking-normal" @change="applyFilters({ venue: $event })" /></label>
                    <label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Period<AppSelect :model-value="filters.range" :options="rangeOptions" size="sm" class="mt-1 min-w-40 normal-case tracking-normal" @change="applyFilters({ range: $event })" /></label>
                </div>
            </header>

            <div class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm leading-6 text-sky-950">
                <strong>What these numbers mean:</strong> Booking Links measure traffic sent to an external page. They do not count confirmed bookings unless that external platform provides real conversion data.
            </div>

            <div v-if="!selectedVenue" class="app-card p-10 text-center">
                <h2 class="text-xl font-semibold text-slate-950">Create a venue first</h2>
                <p class="mt-2 text-sm text-slate-500">A booking destination and its tracking links belong to a venue.</p>
                <Link href="/owner/venues/create" class="mt-5 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Create venue</Link>
            </div>

            <template v-else>
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Traffic sent</p><p class="mt-4 text-4xl font-semibold text-slate-950">{{ performance.metrics.total_clicks }}</p><p class="mt-2 text-sm text-slate-500">Total clicks</p></div>
                    <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">People reached</p><p class="mt-4 text-4xl font-semibold text-slate-950">{{ performance.metrics.unique_visitors }}</p><p class="mt-2 text-sm text-slate-500">Unique visitors</p></div>
                    <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Top source</p><p class="mt-4 text-2xl font-semibold text-slate-950">{{ performance.top_source?.label || '—' }}</p><p class="mt-2 text-sm text-slate-500">{{ performance.top_source ? `${performance.top_source.clicks} clicks` : 'No clicks yet' }}</p></div>
                    <div class="metric-card"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Top tracking link</p><p class="mt-4 truncate text-2xl font-semibold text-slate-950">{{ performance.top_link?.label || '—' }}</p><p class="mt-2 text-sm text-slate-500">{{ performance.top_link ? `${performance.top_link.clicks} clicks` : 'No clicks yet' }}</p></div>
                    <div class="rounded-2xl bg-court-950 p-5 text-white shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-court-300">External bookings</p><p class="mt-4 text-3xl font-semibold">—</p><p class="mt-2 text-sm leading-6 text-court-100/70">Booking conversion is not available from an external platform.</p></div>
                </section>

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Current booking destination</p><h2 class="mt-1 text-xl font-semibold">Where players should book</h2><p class="mt-2 text-sm leading-6 text-slate-500">Changing this destination keeps every existing FinACourt tracking link working.</p></div>
                    <form class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2" @submit.prevent="saveDestination">
                        <label class="block text-sm font-medium text-slate-700 lg:col-span-2">Booking URL<input v-model="destinationForm.destination_url" type="url" inputmode="url" autocomplete="url" placeholder="https://external-platform.com/my-court" class="mt-2 block w-full rounded-xl border-slate-300 text-sm" required><span v-if="destinationForm.errors.destination_url" class="mt-1 block text-xs text-red-600">{{ destinationForm.errors.destination_url }}</span></label>
                        <label class="block text-sm font-medium text-slate-700">Platform name <span class="font-normal text-slate-400">(optional)</span><input v-model="destinationForm.provider_name" type="text" maxlength="120" placeholder="Your current booking platform" class="mt-2 block w-full rounded-xl border-slate-300 text-sm"><span v-if="destinationForm.errors.provider_name" class="mt-1 block text-xs text-red-600">{{ destinationForm.errors.provider_name }}</span></label>
                        <label class="flex items-center gap-3 self-end rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700"><input v-model="destinationForm.is_active" type="checkbox" class="rounded border-slate-300 text-court-700"><span><strong class="block text-slate-900">Destination is active</strong>Turn this off to stop every external booking link.</span></label>
                        <div class="lg:col-span-2"><button :disabled="destinationForm.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ destinationForm.processing ? 'Saving…' : 'Save Booking Destination' }}</button></div>
                    </form>
                </section>

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Trackable links</p><h2 class="mt-1 text-xl font-semibold">Create a link for each channel</h2><p class="mt-2 text-sm leading-6 text-slate-500">Each short link records its source, then immediately sends the visitor to your current booking page.</p></div>
                    <div v-if="!destination" class="p-8 text-center"><h3 class="font-semibold text-slate-900">Already using another booking platform?</h3><p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Add your current booking page above and FinACourt can create trackable links that send players there.</p></div>
                    <div v-else-if="!destination.is_active" class="p-8 text-center"><h3 class="font-semibold text-slate-900">Your booking destination is turned off</h3><p class="mt-2 text-sm text-slate-500">Turn it on before creating or using tracking links.</p></div>
                    <div v-else class="space-y-7 p-5 sm:p-6">
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <article v-for="channel in defaultChannels" :key="channel.source" class="rounded-2xl border border-slate-200 p-4">
                                <p class="font-semibold text-slate-900">{{ channel.label }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ defaultLink(channel.source) ? `${defaultLink(channel.source).clicks} clicks` : 'No link yet' }}</p>
                                <button v-if="defaultLink(channel.source)" type="button" class="mt-4 w-full rounded-xl bg-court-700 px-3 py-2.5 text-xs font-semibold text-white" @click="copy(defaultLink(channel.source).url, `default-${channel.source}`)">{{ copied === `default-${channel.source}` ? 'Copied' : 'Copy Link' }}</button>
                                <button v-else type="button" class="mt-4 w-full rounded-xl border border-court-200 px-3 py-2.5 text-xs font-semibold text-court-800" @click="createDefault(channel)">Create Link</button>
                            </article>
                        </div>

                        <form class="rounded-2xl border border-slate-200 bg-slate-50 p-5" @submit.prevent="createCustom">
                            <div><p class="eyebrow">Custom campaign</p><h3 class="mt-1 text-lg font-semibold text-slate-950">Create a named campaign link</h3></div>
                            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                                <label class="text-sm font-medium text-slate-700">Source<AppSelect v-model="customForm.source" :options="sourceOptions" size="md" class="mt-2 bg-white" /></label>
                                <label class="text-sm font-medium text-slate-700">Name<input v-model="customForm.label" type="text" maxlength="120" placeholder="Weekend Facebook Promo" class="mt-2 block w-full rounded-xl border-slate-300 text-sm" required><span v-if="customForm.errors.label" class="mt-1 block text-xs text-red-600">{{ customForm.errors.label }}</span></label>
                                <label class="text-sm font-medium text-slate-700">Campaign<input v-model="customForm.campaign" type="text" maxlength="120" placeholder="weekend-promo-september" class="mt-2 block w-full rounded-xl border-slate-300 text-sm" required><span v-if="customForm.errors.campaign" class="mt-1 block text-xs text-red-600">{{ customForm.errors.campaign }}</span></label>
                            </div>
                            <button :disabled="customForm.processing" class="mt-5 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">Generate Link</button>
                        </form>
                    </div>
                </section>

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Where visitors came from</p><h2 class="mt-1 text-xl font-semibold">Source breakdown</h2></div>
                    <div v-if="performance.sources.length" class="grid gap-3 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                        <div v-for="source in performance.sources" :key="source.source" class="rounded-2xl border border-slate-100 p-4 shadow-sm"><div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-slate-900">{{ source.label }}</p><p class="mt-1 text-sm text-slate-500">{{ source.unique_visitors }} unique visitors</p></div><p class="text-xl font-semibold text-court-800">{{ source.clicks }}</p></div><p class="mt-3 text-xs text-slate-400">Clicks · Bookings unavailable</p></div>
                    </div>
                    <p v-else class="px-6 py-10 text-center text-sm text-slate-500">Source performance will appear after someone opens a tracking link.</p>
                </section>

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Manage links</p><h2 class="mt-1 text-xl font-semibold">All tracking links</h2></div>
                    <div v-if="links.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3">Link</th><th class="px-5 py-3">Source</th><th class="px-5 py-3">Destination</th><th class="px-5 py-3">Traffic</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Created</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="link in links" :key="link.id"><td class="px-5 py-4"><p class="font-medium text-slate-900">{{ link.label }}</p><p class="mt-1 max-w-64 truncate text-xs text-slate-400">{{ link.url }}</p></td><td class="px-5 py-4 text-slate-600">{{ link.source_label }}<span v-if="link.campaign" class="mt-1 block text-xs text-slate-400">{{ link.campaign }}</span></td><td class="px-5 py-4"><p class="max-w-44 truncate text-slate-600">{{ destination.host }}</p><span class="mt-1 block text-xs text-slate-400">Current booking page</span></td><td class="px-5 py-4"><strong>{{ link.clicks }} clicks</strong><span class="mt-1 block text-xs text-slate-400">{{ link.unique_visitors }} unique</span></td><td class="px-5 py-4"><span :class="link.is_active ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-500'" class="rounded-full px-2.5 py-1 text-xs font-semibold">{{ link.is_active ? 'Active' : 'Disabled' }}</span></td><td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ link.created_at }}</td><td class="px-5 py-4"><div class="flex justify-end gap-3 whitespace-nowrap text-xs font-semibold"><button type="button" class="text-court-800" @click="copy(link.url, `row-${link.id}`)">{{ copied === `row-${link.id}` ? 'Copied' : 'Copy' }}</button><button type="button" class="text-slate-600" @click="rename(link)">Rename</button><button type="button" class="text-slate-600" @click="toggle(link)">{{ link.is_active ? 'Disable' : 'Enable' }}</button><button type="button" class="text-red-600" @click="remove(link)">Delete</button></div></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="px-6 py-10 text-center text-sm text-slate-500">No tracking links created for this venue yet.</p>
                </section>

                <section v-if="qrLinks.length" class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">QR Code</p><h2 class="mt-1 text-xl font-semibold">Download and display</h2><p class="mt-2 text-sm text-slate-500">The QR code opens the FinACourt tracking link, records the scan, then sends the player to your external booking page.</p></div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4"><article v-for="link in qrLinks" :key="link.id" class="rounded-2xl border border-slate-200 p-4 text-center"><img :src="link.qr_url" :alt="`${link.label} QR code`" class="theme-keep-light mx-auto aspect-square w-44 rounded-xl bg-white"><p class="mt-3 font-semibold text-slate-900">{{ link.label }}</p><p class="mt-1 text-xs text-slate-400">{{ link.clicks }} scans · {{ link.unique_visitors }} unique</p><div class="mt-4 flex justify-center gap-3 text-xs font-semibold"><button type="button" class="text-court-800" @click="copy(link.url, `qr-${link.id}`)">Copy Link</button><a :href="link.qr_url" download class="text-court-800">Download SVG</a></div></article></div>
                </section>
            </template>
        </div>
    </OwnerLayout>
</template>
