<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DirectoryListingForm from '../../../Components/DirectoryListingForm.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
const props = defineProps({ listing: Object, sports: Array, sourceTypes: Array, weekdays: Array, locationParents: Array });
const copied = ref(false);
const issuingInvitation = ref(false);
const invitationError = ref('');
const issuedInvitation = ref(null);
const activeInvitation = ref(props.listing.active_claim_invitation);
function action(name) {
    let payload = {};
    if (name === 'verify') { const verification_notes = window.prompt('What did you check, and where did you check it?'); if (!verification_notes) return; payload = { verification_notes }; }
    if (name === 'close' || name === 'remove') { const reason = window.prompt(`Why should this venue be marked ${name === 'close' ? 'closed' : 'hidden'}?`); if (!reason) return; payload = { reason }; }
    router.post(`/platform/directory/${props.listing.slug}/${name}`, payload, { preserveScroll: true });
}
function claimedVenueAction(name) {
    const promptText = name === 'verify-claimed-venue'
        ? 'Describe the completed marketplace review, including the venue details and active court you checked:'
        : 'Explain the ownership dispute or safety reason for removing marketplace access:';
    const value = window.prompt(promptText);
    if (!value) return;
    const payload = name === 'verify-claimed-venue' ? { verification_notes: value } : { reason: value };
    router.post(`/platform/directory/${props.listing.slug}/${name}`, payload, { preserveScroll: true });
}
async function issueClaimInvitation() {
    if (!window.confirm('Create a new private link? Any earlier unused link for this venue will stop working.')) return;
    copied.value = false;
    invitationError.value = '';
    issuingInvitation.value = true;

    try {
        const response = await fetch(`/platform/directory/${props.listing.slug}/claim-invitations`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: '{}',
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {})[0]?.[0] || 'The private link could not be created.');
        issuedInvitation.value = payload.claim_invitation;
        activeInvitation.value = payload.invitation;
    } catch (error) {
        invitationError.value = error instanceof Error ? error.message : 'The private link could not be created.';
    } finally {
        issuingInvitation.value = false;
    }
}
function revokeClaimInvitation() {
    const invitation = activeInvitation.value;
    if (!invitation || !window.confirm('Stop this private link from working?')) return;
    router.delete(`/platform/directory/${props.listing.slug}/claim-invitations/${invitation.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            activeInvitation.value = null;
            issuedInvitation.value = null;
        },
    });
}
async function copyInvitation() {
    if (!issuedInvitation.value?.url) return;
    await navigator.clipboard.writeText(issuedInvitation.value.url);
    copied.value = true;
}
</script>
<template><Head :title="`Edit ${listing.name}`" /><PlatformLayout><div class="mx-auto max-w-5xl"><Link href="/platform/directory" class="text-sm font-semibold text-court-700">← Public venue guide</Link><div class="my-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Venue details</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ listing.name }}</h1><p class="mt-2 text-sm text-slate-500">{{ listing.status_label }} · Last checked {{ listing.last_verified_at || 'never' }}</p></div><div class="flex flex-wrap gap-2"><a :href="`/directory/${listing.slug}`" target="_blank" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold">View public page ↗</a><button v-if="!['claimed','removed'].includes(listing.status)" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold" @click="action('verify')">Mark details checked</button><button v-if="listing.status !== 'published' && !['claimed','removed'].includes(listing.status)" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="action('publish')">Show publicly</button><button v-if="listing.status === 'published'" class="rounded-xl border border-amber-200 px-4 py-2.5 text-sm font-semibold text-amber-800" @click="action('close')">Mark as closed</button><button v-if="!['claimed','removed'].includes(listing.status)" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="action('remove')">Hide from guide</button></div></div>
<section v-if="listing.claimed_venue" class="app-card mb-7 p-6"><div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between"><div><p class="eyebrow">Second safety gate</p><h2 class="mt-2 text-xl font-semibold">Marketplace review for {{ listing.claimed_venue.name }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">Owned by {{ listing.claimed_venue.organization }}. Ownership approval only created private access; it did not authorize public bookings.</p><div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold"><span class="rounded-full bg-slate-100 px-3 py-1.5">{{ listing.claimed_venue.is_published ? 'Owner requested publication' : 'Owner still preparing' }}</span><span class="rounded-full bg-slate-100 px-3 py-1.5">{{ listing.claimed_venue.active_resources_count }} active courts</span><span :class="['rounded-full px-3 py-1.5', listing.claimed_venue.is_marketplace_verified ? 'bg-court-50 text-court-800' : 'bg-amber-50 text-amber-800']">{{ listing.claimed_venue.is_marketplace_verified ? 'Marketplace approved' : 'Marketplace blocked' }}</span></div></div><div class="flex flex-wrap gap-2"><button v-if="!listing.claimed_venue.is_marketplace_verified" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white" @click="claimedVenueAction('verify-claimed-venue')">Complete marketplace review</button><button v-else class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="claimedVenueAction('revoke-claimed-venue')">Remove marketplace access</button></div></div><p class="mt-4 rounded-xl bg-amber-50 p-4 text-xs leading-5 text-amber-900">Before approval, confirm the ownership-proof audit, venue identity, contact details, active court, pricing, and publication request. A dispute removes verification and unpublishes the venue immediately.</p></section>
<section v-if="listing.status !== 'claimed'" class="app-card mb-7 p-6">
    <p class="eyebrow">Private owner invitation</p>
    <div class="mt-2 flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="max-w-2xl">
            <h2 class="text-xl font-semibold">Send a private link to the venue owner</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">First contact the real owner using a venue email, official phone number, or Messenger account you have checked. Then create one private link and send it yourself. The secret is already inside the link.</p>
            <p class="mt-3 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">The link only lets someone ask for access. FinACourt must still confirm ownership independently and wait through the safety period before adding the venue to an account.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <button v-if="listing.is_claimable" type="button" :disabled="issuingInvitation" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-wait disabled:opacity-60" @click="issueClaimInvitation">{{ issuingInvitation ? 'Creating link…' : (activeInvitation ? 'Replace private link' : 'Create private link') }}</button>
            <button v-if="activeInvitation" type="button" class="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700" @click="revokeClaimInvitation">Stop current link</button>
        </div>
    </div>
    <p v-if="!listing.is_claimable" class="mt-4 text-sm text-slate-500">Check and publish this venue before creating an owner link.</p>
    <p v-else-if="activeInvitation && !issuedInvitation" class="mt-4 text-sm text-slate-500">A private link is active until {{ activeInvitation.expires_at }}. Its secret cannot be shown again; replace it if you no longer have the original.</p>
    <p v-if="invitationError" class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ invitationError }}</p>
    <div v-if="issuedInvitation" class="mt-5 rounded-2xl border border-court-200 bg-court-50 p-4">
        <p class="font-semibold text-court-950">Copy this link now</p>
        <p class="mt-1 text-sm text-court-800">It expires {{ issuedInvitation.expires_at }} and will not be shown again after you leave or refresh this page.</p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input :value="issuedInvitation.url" readonly class="min-w-0 flex-1 rounded-xl border border-court-200 bg-white px-3 py-2.5 text-sm" aria-label="Private venue owner link">
            <button type="button" class="rounded-xl bg-court-800 px-4 py-2.5 text-sm font-semibold text-white" @click="copyInvitation">{{ copied ? 'Copied' : 'Copy link' }}</button>
        </div>
    </div>
</section>
<DirectoryListingForm v-if="listing.status !== 'claimed'" :listing="listing" :sports="sports" :source-types="sourceTypes" :weekdays="weekdays" :location-parents="locationParents" /><section class="app-card mt-7 p-6"><h2 class="text-lg font-semibold">Change history</h2><p class="mt-1 text-sm text-slate-500">A simple record of who changed this venue and when.</p><div class="mt-4 divide-y divide-slate-100"><div v-for="audit in listing.audits" :key="`${audit.event_type}-${audit.occurred_at}`" class="py-3"><div class="flex justify-between gap-4 text-sm"><strong>{{ audit.event_type }}</strong><span class="text-slate-400">{{ audit.occurred_at }}</span></div><p class="mt-1 text-xs text-slate-500">{{ audit.actor }}</p></div></div></section></div></PlatformLayout></template>
