<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ claims: Array });
const page = usePage();
const proofCodes = reactive({});

function cancel(claim) {
    if (window.confirm('Cancel this request?')) router.delete(`/owner/directory-claims/${claim.id}`);
}

function verifyCode(claim) {
    router.post(`/owner/directory-claims/${claim.id}/proof/verify`, { code: proofCodes[claim.id] || '' }, { preserveScroll: true });
}

function resendCode(claim) {
    router.post(`/owner/directory-claims/${claim.id}/proof/email`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Your venue requests" />
    <OwnerLayout>
        <div class="mx-auto max-w-5xl space-y-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Bring your venue to FinACourt</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Claim a listed venue</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">A request does not give instant access. FinACourt checks that you are connected to the venue before adding it to your account.</p></div><a href="/directory" class="rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white">Find my venue</a></div>

            <section class="space-y-4">
                <article v-for="claim in claims" :key="claim.id" class="app-card p-5 sm:p-6">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"><div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ claim.status_label }}</span><span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">{{ claim.proof_status_label }}</span><span class="text-xs text-slate-400">{{ claim.created_at }}</span></div><h2 class="mt-3 text-xl font-semibold">{{ claim.listing.name }}</h2><p class="mt-1 text-sm text-slate-500">{{ claim.listing.city }}, {{ claim.listing.province }} · {{ claim.relationship }}</p><p v-if="claim.review_notes" class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Note from FinACourt: {{ claim.review_notes }}</p></div><div class="flex flex-wrap gap-2"><a :href="`/directory/${claim.listing.slug}`" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">View public page</a><Link v-if="claim.approved_venue" :href="`/owner/venues/${claim.approved_venue.id}`" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white">Finish venue setup</Link><button v-if="claim.status === 'pending'" type="button" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="cancel(claim)">Cancel request</button></div></div>
                    <div v-if="claim.status === 'pending'" class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-if="claim.proof_status === 'verified'" class="text-sm leading-6 text-court-900"><strong>Venue email confirmed.</strong> FinACourt still waits until {{ claim.approval_available_at }} before final approval, so suspicious requests can be reported.</div>
                        <p v-else-if="claim.proof_status === 'locked'" class="text-sm leading-6 text-slate-600"><strong>Email-code tries are locked.</strong> FinACourt must now check ownership another safe way.</p>
                        <div v-else-if="claim.can_request_email_code">
                            <p class="text-sm leading-6 text-slate-600">Enter the six-digit code sent to {{ claim.proof_destination || 'the venue’s public email' }}. This proves you can access a venue contact FinACourt already found.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row"><input v-model="proofCodes[claim.id]" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" aria-label="Venue email verification code" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm" placeholder="6-digit code"><button type="button" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="verifyCode(claim)">Confirm code</button><button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold" @click="resendCode(claim)">Send a new code</button></div>
                            <p v-if="page.props.errors?.code" class="mt-2 text-xs text-red-600">{{ page.props.errors.code }}</p>
                        </div>
                        <p v-else class="text-sm leading-6 text-slate-600">This listing has no public venue email. FinACourt must check an official phone number, venue email, business document, or in-person visit. The contact you typed is helpful, but not proof by itself.</p>
                    </div>
                </article>
                <div v-if="!claims.length" class="app-card px-6 py-14 text-center"><h2 class="text-lg font-semibold">No requests yet</h2><p class="mt-2 text-sm text-slate-500">Browse the local venue guide, open your venue, then choose “Yes, this is my venue.”</p></div>
            </section>
        </div>
    </OwnerLayout>
</template>
