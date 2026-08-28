<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({ reviews: Array, filters: Object, statusOptions: Array, pagination: Object });
const filter = useForm({ status: props.filters.status });

function applyFilter() {
    router.get('/platform/reviews', { status: filter.status }, { preserveState: true, replace: true });
}

function moderate(review, status) {
    const note = status === 'rejected' ? window.prompt('Moderation note for the player (optional):') : null;
    if (status === 'rejected' && note === null) return;

    router.patch(`/platform/reviews/${review.id}`, { status, moderation_note: note }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Review moderation" />
    <PlatformLayout>
        <div class="space-y-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Marketplace trust</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Review moderation</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Every submission is linked to a completed player booking. Publish useful feedback or reject abusive and irrelevant content.</p>
                </div>
                <form class="app-card flex items-end gap-3 p-3" @submit.prevent="applyFilter">
                    <label class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Status<AppSelect v-model="filter.status" :options="statusOptions" size="sm" class="mt-1 min-w-36 normal-case tracking-normal" aria-label="Review status" /></label>
                    <button class="rounded-lg bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
                </form>
            </div>

            <section class="space-y-4">
                <article v-for="review in reviews" :key="review.id" class="app-card p-5 sm:p-6">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3"><span class="text-lg tracking-wide text-amber-400">{{ '★'.repeat(review.rating) }}<span class="text-slate-200">{{ '★'.repeat(5 - review.rating) }}</span></span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Verified booking</span></div>
                            <p v-if="review.body" class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ review.body }}</p>
                            <p v-else class="mt-4 text-sm italic text-slate-400">Rating only—no written review.</p>
                            <dl class="mt-5 grid gap-3 text-xs text-slate-500 sm:grid-cols-2 xl:grid-cols-4"><div><dt class="font-semibold uppercase tracking-wider text-slate-400">Venue</dt><dd class="mt-1">{{ review.venue.name }} · {{ review.venue.city }}</dd></div><div><dt class="font-semibold uppercase tracking-wider text-slate-400">Player</dt><dd class="mt-1">{{ review.player.name }} · {{ review.player.email }}</dd></div><div><dt class="font-semibold uppercase tracking-wider text-slate-400">Booking</dt><dd class="mt-1">{{ review.booking.reference }} · ended {{ review.booking.ended_at }}</dd></div><div><dt class="font-semibold uppercase tracking-wider text-slate-400">Submitted</dt><dd class="mt-1">{{ review.created_at }}</dd></div></dl>
                            <p v-if="review.moderation_note" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">Moderation note: {{ review.moderation_note }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a :href="`/venues/${review.venue.slug}`" target="_blank" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">View venue ↗</a>
                            <button v-if="review.status !== 'published'" type="button" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="moderate(review, 'published')">Publish</button>
                            <button v-if="review.status !== 'rejected'" type="button" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="moderate(review, 'rejected')">Reject</button>
                        </div>
                    </div>
                </article>
                <div v-if="!reviews.length" class="app-card px-6 py-14 text-center"><h2 class="text-lg font-semibold">No reviews in this queue</h2><p class="mt-2 text-sm text-slate-500">New booking-verified submissions will appear here.</p></div>
            </section>

            <nav v-if="pagination.last_page > 1" class="flex items-center justify-between"><a :href="pagination.previous || undefined" :aria-disabled="!pagination.previous" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">Previous</a><span class="text-sm text-slate-500">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span><a :href="pagination.next || undefined" :aria-disabled="!pagination.next" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">Next</a></nav>
        </div>
    </PlatformLayout>
</template>
