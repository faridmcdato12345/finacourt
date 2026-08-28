<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venues: Array, pagination: Object });
const totals = computed(() => ({
    venues: props.venues.length,
    courts: props.venues.reduce((sum, venue) => sum + Number(venue.resources_count), 0),
    active: props.venues.reduce((sum, venue) => sum + Number(venue.active_resources_count), 0),
    draft: props.venues.reduce((sum, venue) => sum + Number(venue.resources_count - venue.active_resources_count), 0),
}));
</script>

<template>
    <Head title="Venues and courts" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem]">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Your places</p><h2 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Venues & courts</h2><p class="mt-2 text-sm text-slate-500">Add your venues, courts, opening hours, prices, and photos.</p></div><Link href="/owner/venues/create" class="rounded-xl bg-court-700 px-5 py-3 text-center text-sm font-semibold text-white shadow-sm">+ Add venue</Link></div>

            <div class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><section class="metric-card"><p class="text-sm text-slate-500">Venues on this page</p><p class="mt-3 text-3xl font-semibold">{{ totals.venues }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Total courts</p><p class="mt-3 text-3xl font-semibold">{{ totals.courts }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Bookable courts</p><p class="mt-3 text-3xl font-semibold text-court-700">{{ totals.active }}</p></section><section class="metric-card"><p class="text-sm text-slate-500">Not bookable</p><p class="mt-3 text-3xl font-semibold text-slate-500">{{ totals.draft }}</p></section></div>

            <div v-if="venues.length" class="mt-7 space-y-4">
                <article v-for="venue in venues" :key="venue.id" class="app-card overflow-hidden"><div class="grid gap-5 p-5 sm:grid-cols-[7rem_1fr_auto] sm:items-center sm:p-6"><div class="court-visual h-24 rounded-xl"></div><div><div class="flex flex-wrap items-center gap-2"><h3 class="text-xl font-semibold">{{ venue.name }}</h3><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', venue.is_published && (!venue.requires_platform_review || venue.is_verified) ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-500']">{{ venue.is_published && (!venue.requires_platform_review || venue.is_verified) ? 'Published' : venue.is_published ? 'Waiting for check' : 'Draft' }}</span><span v-if="venue.is_verified" class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">Checked by FinACourt</span></div><p class="mt-2 text-sm text-slate-500">{{ venue.city }}, {{ venue.province }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="sport in venue.sports" :key="sport" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">{{ sport }}</span></div></div><Link :href="`/owner/venues/${venue.id}`" class="rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700">View venue</Link></div><div class="grid border-t border-slate-100 bg-slate-50/70 sm:grid-cols-3"><div class="px-5 py-4 sm:px-6"><p class="text-xs uppercase tracking-wider text-slate-400">Courts</p><p class="mt-1 font-semibold">{{ venue.resources_count }}</p></div><div class="border-t border-slate-100 px-5 py-4 sm:border-l sm:border-t-0 sm:px-6"><p class="text-xs uppercase tracking-wider text-slate-400">Bookable</p><p class="mt-1 font-semibold text-court-700">{{ venue.active_resources_count }}</p></div><div class="border-t border-slate-100 px-5 py-4 sm:border-l sm:border-t-0 sm:px-6"><p class="text-xs uppercase tracking-wider text-slate-400">Public page</p><a v-if="venue.is_published && (!venue.requires_platform_review || venue.is_verified)" :href="`/venues/${venue.slug}`" target="_blank" class="mt-1 inline-block text-sm font-semibold text-court-700">Open ↗</a><p v-else class="mt-1 text-sm text-slate-400">{{ venue.is_published ? 'Waiting for FinACourt check' : 'Not published' }}</p></div></div></article>
            </div>

            <div v-else class="app-card mt-8 px-6 py-16 text-center"><div class="court-visual mx-auto size-16 rounded-2xl"></div><h3 class="mt-5 text-lg font-semibold">Add your first venue</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Start with your location, then add courts, hours, amenities, and prices.</p><Link href="/owner/venues/create" class="mt-6 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Create venue</Link></div>

            <nav v-if="pagination.last_page > 1" class="mt-7 flex items-center justify-between gap-4" aria-label="Venue pages"><Link v-if="pagination.previous" :href="pagination.previous" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">← Previous</Link><span v-else></span><span class="text-sm text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span><Link v-if="pagination.next" :href="pagination.next" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">Next →</Link><span v-else></span></nav>
        </div>
    </OwnerLayout>
</template>
