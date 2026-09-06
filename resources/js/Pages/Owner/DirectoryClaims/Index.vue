<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ claims: Array });
const page = usePage();

function cancel(claim) {
    if (window.confirm('Cancel this request?')) router.delete(`/owner/directory-claims/${claim.id}`);
}
</script>

<template>
    <Head title="Your venue requests" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl space-y-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Bring your venue to FinACourt</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Your venue ownership requests</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">FinACourt checks your connection before adding a pre-created directory venue to your workspace. Approval adds it privately so you can finish setup before publishing.</p></div><a href="/directory" class="rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white">Find my venue</a></div>

            <section class="space-y-4">
                <article v-for="claim in claims" :key="claim.id" class="app-card p-5 sm:p-6">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ claim.status_label }}</span><span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">{{ claim.proof_status_label }}</span><span class="text-xs text-slate-400">{{ claim.created_at }}</span></div><h2 class="mt-3 text-xl font-semibold">{{ claim.listing.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ claim.listing.city }}, {{ claim.listing.province }} · {{ claim.relationship }}</p><p v-if="claim.review_notes" class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Note from FinACourt: {{ claim.review_notes }}</p></div><div class="flex flex-wrap gap-2"><a :href="`/directory/${claim.listing.slug}`" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">View public page</a><Link v-if="claim.approved_venue" :href="`/owner/venues/${claim.approved_venue.id}`" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Finish venue setup</Link><button v-if="claim.status === 'pending'" type="button" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="cancel(claim)">Cancel request</button></div></div>
                    <div v-if="claim.status === 'pending'" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-if="claim.proof_status === 'verified'" class="text-sm leading-6 text-court-900">
                            <strong>FinACourt completed the independent ownership check.</strong>
                            <span v-if="claim.approval_available_at"> Final approval is available after {{ claim.approval_available_at }}, unless a platform administrator uses the recorded early-approval override.</span>
                        </div>
                        <div v-else>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-court-700">Submitted for independent review</p>
                            <h3 class="mt-1 text-base font-semibold text-slate-900">No additional verification code is required</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">Your FinACourt account email <strong>{{ page.props.auth?.user?.email }}</strong> is already verified. FinACourt will separately confirm your connection to <strong>{{ claim.listing.name }}</strong> using an official public venue contact, business evidence, or an in-person check.</p>
                            <p class="mt-2 text-sm leading-6 text-slate-600">The contact and explanation you submitted help with follow-up, but they are not treated as ownership proof by themselves. You can wait here for the platform review.</p>
                        </div>
                    </div>
                </article>
                <div v-if="!claims.length" class="app-card px-6 py-14 text-center"><h2 class="text-lg font-semibold">No requests yet</h2><p class="mt-2 text-sm text-slate-500">Browse the local venue guide, open your venue, then choose “Yes, this is my venue.”</p></div>
            </section>
        </div>
    </OwnerLayout>
</template>
