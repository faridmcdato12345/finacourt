<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({
    ownershipReviews: { type: Array, default: () => [] },
    marketplaceReviews: { type: Array, default: () => [] },
    completedApplications: { type: Array, default: () => [] },
});

const notes = reactive(Object.fromEntries(
    [...props.ownershipReviews, ...props.marketplaceReviews].map((application) => [application.id, '']),
));

function act(application, action) {
    const reviewNotes = (notes[application.id] || '').trim();
    if (reviewNotes.length < 20) return;

    const label = action === 'approve'
        ? 'approve private owner access'
        : action === 'reject'
            ? 'send this application back for changes'
            : 'make this venue eligible to appear publicly';

    if (!window.confirm(`Confirm that you want to ${label}?`)) return;

    router.post(`/platform/venue-applications/${application.id}/${action}`, {
        review_notes: reviewNotes,
    }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Venue applications" />
    <PlatformLayout>
        <div class="space-y-10">
            <header>
                <p class="eyebrow">Owner onboarding</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Venue applications</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Review venues submitted without a private invitation. Ownership approval only unlocks private setup; a separate final review controls public discovery and bookings.</p>
            </header>

            <section class="space-y-4">
                <div>
                    <p class="eyebrow">First gate</p>
                    <h2 class="mt-2 text-2xl font-semibold">Ownership reviews</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Confirm the venue through an independently sourced contact or trustworthy record. Applicant-entered details are context, not proof.</p>
                </div>

                <article v-for="application in ownershipReviews" :key="application.id" class="app-card p-6">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <div>
                            <div class="flex flex-wrap gap-2">
                                <span :class="['rounded-full px-3 py-1 text-xs font-semibold', application.status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-900']">{{ application.status_label }}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Submitted {{ application.submitted_at }}</span>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold">{{ application.venue.name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ application.venue.address }} · {{ application.venue.city }}, {{ application.venue.province }}</p>
                            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                                <div><dt class="text-slate-400">Owner account</dt><dd class="mt-1 font-medium">{{ application.organization.name }}</dd></div>
                                <div><dt class="text-slate-400">Applicant</dt><dd class="mt-1 font-medium">{{ application.requester.name }} · {{ application.requester.email }}</dd></div>
                                <div><dt class="text-slate-400">Venue contact</dt><dd class="mt-1 font-medium">{{ application.venue.phone || 'No phone' }} · {{ application.venue.email || 'No email' }}</dd></div>
                                <div><dt class="text-slate-400">Website</dt><dd class="mt-1 font-medium">{{ application.venue.website || 'Not provided' }}</dd></div>
                            </dl>
                            <p v-if="application.review_notes" class="mt-5 rounded-xl bg-red-50 p-4 text-sm leading-6 text-red-800"><strong>Previous review:</strong> {{ application.review_notes }}</p>
                        </div>
                        <div v-if="application.status === 'pending'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <label :for="`ownership-note-${application.id}`" class="text-sm font-semibold text-slate-800">Independent review note</label>
                            <textarea :id="`ownership-note-${application.id}`" v-model="notes[application.id]" rows="6" minlength="20" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Record the independent source checked and what it confirmed."></textarea>
                            <p class="mt-2 text-xs text-slate-500">At least 20 characters. Do not include identity documents or sensitive numbers.</p>
                            <div class="mt-4 grid gap-2">
                                <button :disabled="notes[application.id]?.trim().length < 20" class="rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40" @click="act(application, 'approve')">Approve private setup</button>
                                <button :disabled="notes[application.id]?.trim().length < 20" class="rounded-xl border border-red-200 bg-white px-4 py-3 text-sm font-semibold text-red-700 disabled:opacity-40" @click="act(application, 'reject')">Request changes</button>
                            </div>
                        </div>
                        <div v-else class="rounded-2xl bg-slate-50 p-5 text-sm leading-6 text-slate-600">The owner can correct the venue details and save to resubmit it. No setup or publication access is available while changes are required.</div>
                    </div>
                </article>

                <div v-if="!ownershipReviews.length" class="app-card p-10 text-center">
                    <h3 class="font-semibold">No ownership applications waiting</h3>
                    <p class="mt-2 text-sm text-slate-500">New non-invited venue applications will appear here.</p>
                </div>
            </section>

            <section class="space-y-4">
                <div>
                    <p class="eyebrow">Second gate</p>
                    <h2 class="mt-2 text-2xl font-semibold">Final marketplace reviews</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Approve only after checking the public details, active courts, pricing, photos, hours, and the owner’s publication request.</p>
                </div>

                <article v-for="application in marketplaceReviews" :key="application.id" class="app-card p-6">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <div>
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">Ownership approved</span>
                                <span :class="['rounded-full px-3 py-1 text-xs font-semibold', application.venue.marketplace_review_requested_at ? 'bg-amber-50 text-amber-900' : 'bg-slate-100 text-slate-500']">{{ application.venue.marketplace_review_requested_at ? `Final review requested ${application.venue.marketplace_review_requested_at}` : 'Owner still preparing' }}</span>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold">{{ application.venue.name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ application.venue.city }}, {{ application.venue.province }} · {{ application.organization.name }}</p>
                            <p class="mt-4 text-sm text-slate-600">{{ application.venue.active_resources_count }} active court{{ application.venue.active_resources_count === 1 ? '' : 's' }} · {{ application.venue.is_published ? 'Owner requested publication' : 'Still private' }}</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <label :for="`marketplace-note-${application.id}`" class="text-sm font-semibold text-slate-800">Final review note</label>
                            <textarea :id="`marketplace-note-${application.id}`" v-model="notes[application.id]" rows="5" minlength="20" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Record what was checked before publication."></textarea>
                            <button :disabled="!application.venue.marketplace_review_requested_at || notes[application.id]?.trim().length < 20" class="mt-4 w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40" @click="act(application, 'verify-marketplace')">Approve for players</button>
                        </div>
                    </div>
                </article>

                <div v-if="!marketplaceReviews.length" class="app-card p-10 text-center">
                    <h3 class="font-semibold">No final reviews waiting</h3>
                    <p class="mt-2 text-sm text-slate-500">Approved owners appear here while they finish private setup.</p>
                </div>
            </section>

            <section v-if="completedApplications.length" class="app-card overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-5"><h2 class="text-xl font-semibold">Recently completed</h2></div>
                <div class="divide-y divide-slate-100">
                    <div v-for="application in completedApplications" :key="application.id" class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-semibold">{{ application.venue.name }}</p><p class="mt-1 text-xs text-slate-500">{{ application.organization.name }} · submitted {{ application.submitted_at }}</p></div>
                        <span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">Visible when published</span>
                    </div>
                </div>
            </section>
        </div>
    </PlatformLayout>
</template>
