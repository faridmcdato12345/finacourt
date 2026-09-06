<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    listing: Object,
    organization: Object,
    invitationToken: String,
    invitationExpiresAt: String,
});

const form = useForm({
    relationship_to_venue: 'owner',
    verification_contact: '',
    evidence_details: '',
    venue_confirmation: false,
});

const relationships = [
    { value: 'owner', label: 'I own this venue' },
    { value: 'authorized_manager', label: 'I manage this venue' },
    { value: 'authorized_representative', label: 'I represent the owner' },
];

function submit() {
    form.post(`/owner/venue-invitations/${props.invitationToken}`);
}
</script>

<template>
    <Head :title="`Confirm and claim ${listing.name}`" />
    <OwnerLayout>
        <div class="mx-auto max-w-3xl">
            <Link href="/owner/directory-claims" class="text-sm font-semibold text-court-700">← Your venue requests</Link>

            <header class="mt-5">
                <p class="eyebrow">Private venue invitation</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Confirm and claim {{ listing.name }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    FinACourt pre-created this public directory listing from information it checked before inviting
                    <strong>{{ organization.name }}</strong>. It is not connected to your owner workspace yet.
                    Confirm your relationship below so FinACourt can verify ownership safely.
                </p>
                <p class="mt-2 text-xs leading-5 text-slate-500">This one-time invitation expires {{ invitationExpiresAt }}.</p>
            </header>

            <section class="app-card mt-7 overflow-hidden" aria-labelledby="precreated-listing-heading">
                <div class="border-b border-slate-100 bg-court-50 px-6 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-court-700">Pre-created directory listing</p>
                    <h2 id="precreated-listing-heading" class="mt-1 text-lg font-semibold">Review the venue before confirming</h2>
                </div>
                <div class="p-6">
                    <p class="font-semibold">{{ listing.name }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ listing.address }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ listing.city }}, {{ listing.province }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span v-for="sport in listing.sports" :key="sport" class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ sport }}</span>
                    </div>
                    <p class="mt-5 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">
                        This listing contains public venue facts only. It has no owner access, court inventory, live availability, or booking controls yet.
                    </p>
                </div>
            </section>

            <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-6" aria-labelledby="next-steps-heading">
                <h2 id="next-steps-heading" class="text-lg font-semibold">What happens next</h2>
                <ol class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <li class="rounded-xl bg-slate-50 p-4">
                        <span class="flex size-7 items-center justify-center rounded-full bg-court-700 text-xs font-semibold text-white">1</span>
                        <strong class="mt-3 block">You confirm</strong>
                        <span class="mt-1 block leading-5 text-slate-500">Review the listing and submit your connection details.</span>
                    </li>
                    <li class="rounded-xl bg-slate-50 p-4">
                        <span class="flex size-7 items-center justify-center rounded-full bg-court-700 text-xs font-semibold text-white">2</span>
                        <strong class="mt-3 block">FinACourt verifies</strong>
                        <span class="mt-1 block leading-5 text-slate-500">We use an independent venue contact or a documented manual check.</span>
                    </li>
                    <li class="rounded-xl bg-slate-50 p-4">
                        <span class="flex size-7 items-center justify-center rounded-full bg-court-700 text-xs font-semibold text-white">3</span>
                        <strong class="mt-3 block">You finish setup</strong>
                        <span class="mt-1 block leading-5 text-slate-500">After approval, the venue is added privately to your workspace.</span>
                    </li>
                </ol>
            </section>

            <form class="app-card mt-5 space-y-5 p-6" @submit.prevent="submit">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-court-700">Ownership confirmation</p>
                    <h2 class="mt-1 text-xl font-semibold">Tell us how you are connected</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">These details help FinACourt choose a safe verification method. They do not prove ownership by themselves.</p>
                </div>

                <div v-if="form.errors.listing" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ form.errors.listing }}</div>

                <label class="block text-sm font-semibold text-slate-700">
                    Your role at this venue
                    <AppSelect v-model="form.relationship_to_venue" :options="relationships" class="mt-2" />
                    <span v-if="form.errors.relationship_to_venue" class="mt-1 block text-xs text-red-600">{{ form.errors.relationship_to_venue }}</span>
                </label>

                <label class="block text-sm font-semibold text-slate-700">
                    Contact for verification
                    <input v-model="form.verification_contact" required maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="A business email or phone number where FinACourt can reach you">
                    <span v-if="form.errors.verification_contact" class="mt-1 block text-xs text-red-600">{{ form.errors.verification_contact }}</span>
                </label>

                <label class="block text-sm font-semibold text-slate-700">
                    How can FinACourt verify your connection?
                    <textarea v-model="form.evidence_details" required minlength="30" maxlength="3000" rows="6" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="For example: business registration, lease, utility account, official website, public venue email, phone call, or an in-person visit. Do not upload or paste private IDs here."></textarea>
                    <span v-if="form.errors.evidence_details" class="mt-1 block text-xs text-red-600">{{ form.errors.evidence_details }}</span>
                </label>

                <div class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    Your FinACourt account email is already verified, so no additional code will be sent. After you submit, a platform administrator independently checks the venue through an official public contact, business evidence, or an in-person visit before approval.
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm leading-6 text-slate-700">
                    <input v-model="form.venue_confirmation" required type="checkbox" class="mt-1 size-4 shrink-0 accent-court-700">
                    <span>
                        I confirm that this listing is the venue I own, manage, or am authorized to represent. I understand that submitting this form starts a verification review and does not give immediate access.
                        <span v-if="form.errors.venue_confirmation" class="mt-1 block text-xs text-red-600">{{ form.errors.venue_confirmation }}</span>
                    </span>
                </label>

                <button :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50">
                    {{ form.processing ? 'Submitting confirmation…' : 'Submit ownership confirmation' }}
                </button>
            </form>
        </div>
    </OwnerLayout>
</template>
