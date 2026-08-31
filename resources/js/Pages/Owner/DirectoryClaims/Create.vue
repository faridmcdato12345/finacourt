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
const form = useForm({ relationship_to_venue: 'owner', verification_contact: '', evidence_details: '' });
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
    <Head :title="`Add ${listing.name}`" />
    <OwnerLayout>
        <div class="mx-auto max-w-3xl"><Link href="/owner/directory-claims" class="text-sm font-semibold text-court-700">← Your venue requests</Link><div class="mt-5"><p class="eyebrow">Private venue invitation</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Request access to {{ listing.name }}</h1><p class="mt-3 text-sm leading-6 text-slate-600">FinACourt sent this private link to start a check for <strong>{{ organization.name }}</strong>. The link works once and expires {{ invitationExpiresAt }}. It does not prove ownership by itself, and players cannot book the venue until FinACourt completes the ownership and venue checks.</p></div>

            <div class="app-card mt-7 p-6"><p class="text-sm font-semibold">{{ listing.address }}</p><p class="mt-1 text-sm text-slate-500">{{ listing.city }}, {{ listing.province }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="sport in listing.sports" :key="sport" class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ sport }}</span></div></div>

            <form class="app-card mt-5 space-y-5 p-6" @submit.prevent="submit">
                <div v-if="form.errors.listing" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ form.errors.listing }}</div>
                <label class="block text-sm font-semibold text-slate-700">How are you connected to this venue?<AppSelect v-model="form.relationship_to_venue" :options="relationships" class="mt-2" /><span v-if="form.errors.relationship_to_venue" class="mt-1 block text-xs text-red-600">{{ form.errors.relationship_to_venue }}</span></label>
                <label class="block text-sm font-semibold text-slate-700">Your best contact for follow-up<input v-model="form.verification_contact" required maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="A business email or phone number where FinACourt can reach you"><span v-if="form.errors.verification_contact" class="mt-1 block text-xs text-red-600">{{ form.errors.verification_contact }}</span></label>
                <label class="block text-sm font-semibold text-slate-700">What can FinACourt check?<textarea v-model="form.evidence_details" required minlength="30" maxlength="3000" rows="7" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="Tell us what can confirm your connection: business registration, lease, utility bill, official website, venue email, or public phone number. Do not upload or paste private IDs here."></textarea><span v-if="form.errors.evidence_details" class="mt-1 block text-xs text-red-600">{{ form.errors.evidence_details }}</span></label>
                <div class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">Your contact and explanation help FinACourt check the request, but they are not proof by themselves. When possible, FinACourt sends a code to a venue email already found from a public source. Otherwise, FinACourt checks an official phone number, venue email, business document, or in-person visit.</div>
                <button :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50">Request venue check</button>
            </form>
        </div>
    </OwnerLayout>
</template>
