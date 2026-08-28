<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ listing: Object, organization: Object });
const form = useForm({ relationship_to_venue: 'owner', verification_contact: '', evidence_details: '' });
const relationships = [
    { value: 'owner', label: 'I own this venue' },
    { value: 'authorized_manager', label: 'I manage this venue' },
    { value: 'authorized_representative', label: 'I represent the owner' },
];

function submit() {
    form.post(`/owner/directory/${props.listing.slug}/claim`);
}
</script>

<template>
    <Head :title="`Add ${listing.name}`" />
    <OwnerLayout>
        <div class="mx-auto max-w-3xl"><Link href="/owner/directory-claims" class="text-sm font-semibold text-court-700">← Your venue requests</Link><div class="mt-5"><p class="eyebrow">Ownership review</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Request access to {{ listing.name }}</h1><p class="mt-3 text-sm leading-6 text-slate-600">This form only starts a review. FinACourt must independently confirm your connection before adding a private profile to <strong>{{ organization.name }}</strong>. A separate marketplace check is required before players can book it.</p></div>

            <div class="app-card mt-7 p-6"><p class="text-sm font-semibold">{{ listing.address }}</p><p class="mt-1 text-sm text-slate-500">{{ listing.city }}, {{ listing.province }}</p><div class="mt-3 flex flex-wrap gap-2"><span v-for="sport in listing.sports" :key="sport" class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ sport }}</span></div></div>

            <form class="app-card mt-5 space-y-5 p-6" @submit.prevent="submit">
                <div v-if="form.errors.listing" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ form.errors.listing }}</div>
                <label class="block text-sm font-semibold text-slate-700">How are you connected to this venue?<AppSelect v-model="form.relationship_to_venue" :options="relationships" class="mt-2" /><span v-if="form.errors.relationship_to_venue" class="mt-1 block text-xs text-red-600">{{ form.errors.relationship_to_venue }}</span></label>
                <label class="block text-sm font-semibold text-slate-700">Your best contact for follow-up<input v-model="form.verification_contact" required maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="A business email or phone number where FinACourt can reach you"><span v-if="form.errors.verification_contact" class="mt-1 block text-xs text-red-600">{{ form.errors.verification_contact }}</span></label>
                <label class="block text-sm font-semibold text-slate-700">What should FinACourt check?<textarea v-model="form.evidence_details" required minlength="30" maxlength="3000" rows="7" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="Tell us which official records or contacts can confirm your connection, such as a business registration, lease, utility bill, official website, venue-domain email, or public phone number. Do not upload or paste private IDs here."></textarea><span v-if="form.errors.evidence_details" class="mt-1 block text-xs text-red-600">{{ form.errors.evidence_details }}</span></label>
                <div class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">Your contact and description are supporting information, not proof. When possible, a code is sent to the venue email already found in the public source. Otherwise, FinACourt must confirm through an independently sourced phone number, official-domain email, business documents, or an in-person check.</div>
                <button :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50">Request ownership review</button>
            </form>
        </div>
    </OwnerLayout>
</template>
