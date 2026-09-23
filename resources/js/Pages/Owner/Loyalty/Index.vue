<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    venues: Array,
    selectedVenue: Object,
});

const termsForm = useForm({
    stamps_required: props.selectedVenue?.stamps_required ?? 5,
    discount_percent: props.selectedVenue?.discount_percent ?? '10.00',
    discount_cap: props.selectedVenue?.discount_cap ?? '100.00',
});

watch(() => props.selectedVenue?.id, () => {
    termsForm.defaults({
        stamps_required: props.selectedVenue?.stamps_required ?? 5,
        discount_percent: props.selectedVenue?.discount_percent ?? '10.00',
        discount_cap: props.selectedVenue?.discount_cap ?? '100.00',
    });
    termsForm.reset();
    termsForm.clearErrors();
});

function selectVenue(id) {
    router.get('/owner/loyalty', { venue: id }, { preserveState: true, replace: true });
}

function saveTerms() {
    if (!props.selectedVenue || !window.confirm('Save these loyalty terms for future eligible bookings? Existing earned stamps and rewards keep their original terms.')) return;
    termsForm.patch(`/owner/venues/${props.selectedVenue.id}/loyalty/terms`, { preserveScroll: true });
}

function setLoyalty(active) {
    if (!props.selectedVenue) return;
    const message = active
        ? `Activate loyalty at ${props.selectedVenue.name}? ${props.selectedVenue.stamps_required} stamps will unlock ${props.selectedVenue.discount_percent}% off a court booking, up to ₱${props.selectedVenue.discount_cap}. Your venue funds the discount.`
        : 'Pause loyalty? Bookings made while active can still earn stamps, and existing rewards remain valid.';
    if (window.confirm(message)) {
        router.patch(`/owner/venues/${props.selectedVenue.id}/loyalty`, { active }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Venue loyalty" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl space-y-7">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="eyebrow">Repeat players</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">Venue loyalty</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Choose the reward for each venue, track stamps, and decide when to turn earning on. Loyalty is off until you activate it.</p>
                </div>
                <label v-if="venues.length" class="min-w-56 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Venue
                    <AppSelect :model-value="selectedVenue?.id" :options="venues" option-value="id" option-label="name" aria-label="Select venue for loyalty" class="mt-2 normal-case tracking-normal" @change="selectVenue" />
                </label>
            </header>

            <div v-if="!selectedVenue" class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <h2 class="text-xl font-semibold text-slate-950">Add a venue to get started</h2>
                <p class="mt-2 text-sm text-slate-600">Each venue has its own loyalty settings and rewards.</p>
                <Link href="/owner/venues/create" class="mt-5 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Add venue</Link>
            </div>

            <template v-else>
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="loyalty-status-heading">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-court-700">Program status</p>
                            <h2 id="loyalty-status-heading" class="mt-2 text-2xl font-semibold text-slate-950">{{ selectedVenue.name }}</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Players earn one stamp after each qualifying completed, online-paid game, at most once per venue-local day. They choose when to use an unlocked reward.</p>
                        </div>
                        <span :class="['rounded-full px-3 py-1.5 text-sm font-semibold', selectedVenue.active ? 'bg-court-50 text-court-800' : 'bg-slate-100 text-slate-600']">{{ selectedVenue.active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
                        <button type="button" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800" @click="setLoyalty(!selectedVenue.active)">{{ selectedVenue.active ? 'Pause loyalty' : 'Activate loyalty' }}</button>
                        <Link :href="`/owner/venues/${selectedVenue.id}`" class="text-sm font-semibold text-court-700">View venue →</Link>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="loyalty-insights-heading">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-court-700">Loyalty at a glance</p>
                    <h2 id="loyalty-insights-heading" class="mt-2 text-2xl font-semibold text-slate-950">What happened at this venue</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Completed games and paid reward bookings from the past 30 days. Ready rewards are the current balance.</p>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-2xl bg-court-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Players who returned</p>
                            <p class="mt-2 text-3xl font-semibold text-court-950">{{ selectedVenue.insights.repeat_players }}</p>
                            <p class="mt-1 text-xs leading-5 text-court-800">Played another completed, online-paid game in the past 30 days</p>
                        </div>
                        <div class="rounded-2xl bg-amber-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Rewards ready now</p>
                            <p class="mt-2 text-3xl font-semibold text-amber-950">{{ selectedVenue.insights.rewards_ready }}</p>
                            <p class="mt-1 text-xs leading-5 text-amber-900">Available to players when they choose to book</p>
                        </div>
                        <div class="rounded-2xl bg-sky-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-sky-800">Rewards used</p>
                            <p class="mt-2 text-3xl font-semibold text-sky-950">{{ selectedVenue.insights.rewards_redeemed }}</p>
                            <p class="mt-1 text-xs leading-5 text-sky-900">Paid reward bookings in the past 30 days</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-600">Loyalty discounts</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-950">₱{{ Number(selectedVenue.insights.discount_amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                            <p class="mt-1 text-xs leading-5 text-slate-600">Venue-funded discounts on those paid reward bookings</p>
                        </div>
                    </div>

                    <p class="mt-5 rounded-2xl border border-court-200 bg-court-50 px-4 py-3 text-sm leading-6 text-court-900">
                        <template v-if="selectedVenue.insights.players_one_away">{{ selectedVenue.insights.players_one_away }} {{ selectedVenue.insights.players_one_away === 1 ? 'player is' : 'players are' }} one eligible completed game away from a reward.</template>
                        <template v-else>No players are one eligible completed game away from a reward yet.</template>
                    </p>
                    <p class="mt-3 text-xs leading-5 text-slate-500">Cancelled, refunded, and pending-refund games are excluded. Return visits are observed bookings, not proof that loyalty caused them.</p>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="loyalty-terms-heading">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-court-700">Reward settings</p>
                    <h2 id="loyalty-terms-heading" class="mt-2 text-2xl font-semibold text-slate-950">Set the reward players earn</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Your venue funds the discount on the court price. It stacks with eligible deals: the deal applies first, then loyalty applies to the remaining court price.</p>
                    <form class="mt-6 grid gap-4 sm:grid-cols-3" @submit.prevent="saveTerms">
                        <label class="block text-sm font-medium text-slate-800">Stamps per reward<input v-model="termsForm.stamps_required" type="number" min="1" max="50" step="1" required class="mt-2 w-full rounded-xl border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium text-slate-800">Court discount (%)<input v-model="termsForm.discount_percent" type="number" min="0.01" max="100" step="0.01" required class="mt-2 w-full rounded-xl border-slate-300 px-3 py-2"></label>
                        <label class="block text-sm font-medium text-slate-800">Maximum discount (₱)<input v-model="termsForm.discount_cap" type="number" min="0.01" max="10000" step="0.01" required class="mt-2 w-full rounded-xl border-slate-300 px-3 py-2"></label>
                        <p v-if="Object.keys(termsForm.errors).length" role="alert" class="text-sm text-red-700 sm:col-span-3">{{ Object.values(termsForm.errors)[0] }}</p>
                        <div class="sm:col-span-3"><button type="submit" :disabled="termsForm.processing" class="rounded-xl border border-court-300 bg-white px-5 py-3 text-sm font-semibold text-court-800 hover:bg-court-50 disabled:opacity-50">Save reward terms</button><p class="mt-2 text-xs leading-5 text-slate-500">Existing partial stamp cards finish under their original terms; new reward cycles use updated settings.</p></div>
                    </form>
                </section>

                <div class="rounded-2xl border border-court-200 bg-court-50 px-5 py-4 text-sm leading-6 text-court-900">Rewards do not expire. Pausing stops new bookings from earning stamps but honors earlier promises and rewards already earned. Cancelled or refunded games cannot keep an earned stamp.</div>
            </template>
        </div>
    </OwnerLayout>
</template>
