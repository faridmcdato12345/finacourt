<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({
    venues: Array,
    integrations: Object,
    scoreNote: String,
});

const copied = ref(null);

function createLink(venueId, destination, promotionId = null) {
    router.post(`/owner/venues/${venueId}/visibility-links`, {
        destination,
        promotion_id: promotionId,
    }, { preserveScroll: true });
}

async function copy(value, key) {
    if (!value || !navigator.clipboard) return;
    await navigator.clipboard.writeText(value);
    copied.value = key;
    window.setTimeout(() => { copied.value = null; }, 1800);
}
</script>

<template>
    <Head title="Visibility center" />
    <OwnerLayout>
        <div class="mx-auto max-w-[92rem] space-y-7">
            <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <p class="eyebrow">Help players find you</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">Visibility center</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Improve the listing players see, share booking-ready QR codes, and check what still needs attention. No Google connection is required.</p>
                </div>
                <div class="app-card grid gap-2 p-4 text-xs text-slate-600 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5"><strong class="block text-slate-900">Place search</strong>{{ integrations.places.label }}</div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5"><strong class="block text-slate-900">Business Profile</strong>{{ integrations.business_profile.label }}</div>
                </div>
            </header>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-950">
                <strong>About your score:</strong> {{ scoreNote }}
            </div>

            <div v-if="venues.length === 0" class="app-card p-10 text-center">
                <h2 class="text-xl font-semibold text-slate-950">Create a venue first</h2>
                <p class="mt-2 text-sm text-slate-500">Your visibility checklist will appear after your first venue is saved.</p>
                <Link href="/owner/venues/create" class="mt-5 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Create venue</Link>
            </div>

            <article v-for="venue in venues" :key="venue.id" class="app-card overflow-hidden" data-visibility-venue>
                <div class="grid gap-6 border-b border-slate-200 bg-[linear-gradient(120deg,#f0fbf5_0%,#ffffff_62%)] p-5 sm:p-7 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-semibold tracking-tight text-slate-950">{{ venue.name }}</h2>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-court-800 shadow-sm">{{ venue.city }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-full bg-court-50 px-3 py-1.5 text-court-800">Marketplace: {{ venue.marketplace_status }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">SEO page: {{ venue.seo_status }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">{{ venue.location_status }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">{{ venue.photos_status }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="grid size-24 place-items-center rounded-full border-[9px] border-court-100 bg-white shadow-sm" data-visibility-score>
                            <div class="text-center"><strong class="block text-2xl text-court-900">{{ venue.score }}</strong><span class="text-[10px] font-semibold uppercase text-slate-400">of 100</span></div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <Link :href="venue.edit_url" class="block font-semibold text-court-800 hover:underline">Edit profile →</Link>
                            <Link :href="venue.hours_url" class="block font-semibold text-court-800 hover:underline">Opening hours →</Link>
                            <a v-if="venue.directions_url" :href="venue.directions_url" target="_blank" rel="noopener noreferrer" class="block font-semibold text-court-800 hover:underline">Test directions ↗</a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-7 p-5 sm:p-7 xl:grid-cols-[1.15fr_.85fr]">
                    <section>
                        <div class="flex items-end justify-between gap-4">
                            <div><p class="eyebrow">SEO and listing checklist</p><h3 class="mt-1 text-xl font-semibold text-slate-950">Make the profile useful to players</h3></div>
                            <span class="text-xs text-slate-400">{{ venue.hours_status }}</span>
                        </div>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div v-for="check in venue.checks" :key="check.code" :class="['rounded-2xl border p-4', check.complete ? 'border-court-100 bg-court-50/60' : 'border-slate-200 bg-white']">
                                <div class="flex items-start gap-3">
                                    <span :class="['mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold', check.complete ? 'bg-court-700 text-white' : 'bg-slate-100 text-slate-500']">{{ check.complete ? '✓' : '!' }}</span>
                                    <div><h4 class="text-sm font-semibold text-slate-900">{{ check.label }} <span class="text-slate-400">· {{ check.weight }} pts</span></h4><p class="mt-1 text-xs leading-5 text-slate-500">{{ check.complete ? 'Complete' : check.guidance }}</p></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-5">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p class="eyebrow">Booking links</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">Ready to copy and share</h3>
                            <div v-if="venue.public_url" class="mt-4 space-y-3">
                                <div v-for="item in [
                                    { label: 'Public venue page', value: venue.public_url, key: `public-${venue.id}` },
                                    { label: 'Google-ready booking URL', value: venue.google_booking_url, key: `google-${venue.id}` },
                                ]" :key="item.key">
                                    <label class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</label>
                                    <div class="mt-1 flex gap-2"><input :value="item.value" readonly class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"><button type="button" class="rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-court-800" @click="copy(item.value, item.key)">{{ copied === item.key ? 'Copied' : 'Copy' }}</button></div>
                                </div>
                            </div>
                            <p v-else class="mt-3 text-sm leading-6 text-slate-500">Publish active, bookable inventory to unlock public and booking links.</p>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-5">
                            <p class="eyebrow">QR booking materials</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">Stable, measurable destinations</h3>
                            <p class="mt-2 text-xs leading-5 text-slate-500">Each scan opens a server-checked public page and records QR as an acquisition source. It never bypasses live availability.</p>
                            <div v-if="venue.public_url" class="mt-4 flex flex-wrap gap-2">
                                <button type="button" class="rounded-xl bg-court-700 px-4 py-2.5 text-xs font-semibold text-white" @click="createLink(venue.id, 'venue')">Venue QR</button>
                                <button type="button" class="rounded-xl border border-court-200 px-4 py-2.5 text-xs font-semibold text-court-800" @click="createLink(venue.id, 'booking')">Booking QR</button>
                            </div>
                            <div v-if="venue.promotions.length" class="mt-3 space-y-2">
                                <button v-for="promotion in venue.promotions" :key="promotion.id" type="button" class="block w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-left text-xs font-semibold text-amber-900" @click="createLink(venue.id, 'promotion', promotion.id)">Create QR for {{ promotion.title }}</button>
                            </div>
                            <div v-if="venue.links.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div v-for="link in venue.links" :key="link.id" class="rounded-2xl border border-slate-200 bg-white p-3">
                                    <img :src="link.qr_url" :alt="`${link.label} QR code`" class="mx-auto aspect-square w-36 rounded-xl bg-white">
                                    <p class="mt-2 text-center text-xs font-semibold text-slate-900">{{ link.promotion || link.label }}</p>
                                    <p class="mt-1 text-center text-[11px] text-slate-400">{{ link.visits_count }} visits</p>
                                    <div class="mt-3 flex justify-center gap-2"><a :href="link.url" target="_blank" rel="noopener" class="text-xs font-semibold text-court-800">Open</a><a :href="link.qr_url" download class="text-xs font-semibold text-court-800">Download SVG</a></div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-5">
                            <p class="eyebrow">Google status</p>
                            <h3 class="mt-1 text-lg font-semibold text-slate-950">{{ venue.google_profile.label }}</h3>
                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ venue.google_profile.detail }}</p>
                            <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">Location: {{ venue.place_id_status }}</p>
                        </div>
                    </section>
                </div>
            </article>
        </div>
    </OwnerLayout>
</template>
