<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venue: Object });

const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

function destroyResource(resource) {
    if (window.confirm(`Delete ${resource.name}?`)) {
        router.delete(`/owner/venues/${props.venue.id}/resources/${resource.id}`);
    }
}
</script>

<template>
    <Head :title="venue.name" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <Link href="/owner/venues" class="text-sm font-semibold text-court-700 hover:text-court-800">← All venues</Link>
            <div class="mt-4 flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-3xl font-semibold tracking-tight text-slate-950">{{ venue.name }}</h2>
                        <span :class="['rounded-full px-3 py-1 text-xs font-semibold', venue.is_published && (!venue.requires_platform_review || venue.verified_at) ? 'bg-court-100 text-court-800' : 'bg-slate-200 text-slate-600']">{{ venue.is_published && (!venue.requires_platform_review || venue.verified_at) ? 'Published' : venue.is_published ? 'Marketplace review pending' : 'Draft' }}</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ venue.verified_at ? 'Verified' : 'Verification pending' }}</span>
                    </div>
                    <p class="mt-2 text-slate-600">{{ venue.address }}, {{ venue.city }}, {{ venue.province }}</p>
                    <p v-if="venue.requires_platform_review && !venue.verified_at" class="mt-3 max-w-2xl rounded-xl bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">This claimed venue remains private even when publication is selected. Finish the venue and add an active court; FinACourt must then complete a separate marketplace review before players can find or book it.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="`/owner/venues/${venue.id}/hours`" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Operating hours</Link>
                    <Link :href="`/owner/venues/${venue.id}/edit`" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-court-800">Edit venue</Link>
                </div>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div class="space-y-6">
                    <section id="resources" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div><h3 class="text-lg font-semibold text-slate-950">Courts & resources</h3><p class="mt-1 text-sm text-slate-500">Booking targets and their base prices.</p></div>
                            <Link :href="`/owner/venues/${venue.id}/resources/create`" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Add resource</Link>
                        </div>
                        <div v-if="venue.resources.length" class="mt-6 divide-y divide-slate-100">
                            <div v-for="resource in venue.resources" :key="resource.id" class="flex flex-col gap-4 py-5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-950">{{ resource.name }}</p>
                                        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', resource.is_active ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-500']">{{ resource.is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">{{ resource.sport }} · {{ resource.resource_type }} · {{ resource.setting }} · {{ resource.booking_increment_minutes }} min increments</p>
                                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ money.format(resource.base_hourly_rate) }} / hour</p>
                                </div>
                                <div class="flex gap-2">
                                    <Link :href="`/owner/venues/${venue.id}/resources/${resource.id}/edit`" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit</Link>
                                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" @click="destroyResource(resource)">Delete</button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-6 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No resources configured yet.</p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-950">About & contact</h3>
                        <p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600">{{ venue.description || 'No venue description yet.' }}</p>
                        <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-400">Phone</dt><dd class="mt-1 font-medium text-slate-800">{{ venue.phone || 'Not set' }}</dd></div>
                            <div><dt class="text-slate-400">Email</dt><dd class="mt-1 font-medium text-slate-800">{{ venue.email || 'Not set' }}</dd></div>
                            <div><dt class="text-slate-400">Website</dt><dd class="mt-1 break-all font-medium text-slate-800">{{ venue.website || 'Not set' }}</dd></div>
                            <div><dt class="text-slate-400">Coordinates</dt><dd class="mt-1 font-medium text-slate-800">{{ venue.latitude && venue.longitude ? `${venue.latitude}, ${venue.longitude}` : 'Not set' }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div><h3 class="text-lg font-semibold text-slate-950">Venue photos</h3><p class="mt-1 text-sm text-slate-500">The cover photo appears first on the public venue page.</p></div>
                            <Link :href="`/owner/venues/${venue.id}/edit`" class="text-sm font-semibold text-court-700">Manage</Link>
                        </div>
                        <div v-if="venue.photos.length" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div v-for="photo in venue.photos" :key="photo.id" class="relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100">
                                <img :src="photo.url" :alt="photo.alt_text || `${venue.name} venue photo`" loading="lazy" class="size-full object-cover" />
                                <span v-if="photo.is_primary" class="absolute left-2 top-2 rounded-md bg-court-800 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">Cover</span>
                            </div>
                        </div>
                        <p v-else class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No photos uploaded yet.</p>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between"><h3 class="font-semibold text-slate-950">Operating hours</h3><Link :href="`/owner/venues/${venue.id}/hours`" class="text-sm font-semibold text-court-700">Edit</Link></div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div v-for="hour in venue.operating_hours" :key="hour.day" class="flex justify-between gap-4"><dt class="text-slate-500">{{ hour.day }}</dt><dd class="font-medium text-slate-800">{{ hour.is_closed ? 'Closed' : `${hour.opens_at}–${hour.closes_at}` }}</dd></div>
                        </dl>
                    </section>
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-slate-950">Sports</h3>
                        <div class="mt-4 flex flex-wrap gap-2"><span v-for="sport in venue.sports" :key="sport.id" class="rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800">{{ sport.name }}</span></div>
                    </section>
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-semibold text-slate-950">Amenities</h3>
                        <div v-if="venue.amenities.length" class="mt-4 flex flex-wrap gap-2"><span v-for="amenity in venue.amenities" :key="amenity.id" class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700">{{ amenity.name }}</span></div>
                        <p v-else class="mt-3 text-sm text-slate-500">No amenities selected.</p>
                    </section>
                </aside>
            </div>
        </div>
    </OwnerLayout>
</template>
