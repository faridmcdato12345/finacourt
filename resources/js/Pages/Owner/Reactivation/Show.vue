<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ campaign: Object, eligibility: Object });
const canSend = computed(() => (props.eligibility?.eligible || 0) > 0);

function send() {
    if (!canSend.value) return;
    if (window.confirm(`Send this one-time message to ${props.eligibility.eligible} eligible ${props.eligibility.eligible === 1 ? 'player' : 'players'}?`)) router.post(`/owner/reactivation/${props.campaign.id}/send`);
}
function cancel() { if (window.confirm('Cancel this draft?')) router.patch(`/owner/reactivation/${props.campaign.id}/cancel`); }
function toggleAutomation() { router.patch(`/owner/reactivation/${props.campaign.id}/automation`); }
</script>

<template>
    <Head :title="campaign.title" />
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <Link href="/owner/reactivation" class="text-sm font-semibold text-court-700">← Past players</Link>
            <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ campaign.status_label }}</span>
                        <span v-if="campaign.delivery_mode === 'automated'" class="rounded-full bg-court-50 px-2.5 py-1 text-xs font-semibold text-court-800">Checks after {{ campaign.inactivity_days }} inactive days</span>
                        <span class="text-xs text-slate-400">Uses each player’s enabled channels</span>
                    </div>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight">{{ campaign.title }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ campaign.venue }}<span v-if="campaign.sport"> · {{ campaign.sport }}</span> · {{ campaign.segment_label }}</p>
                </div>
                <div v-if="campaign.status === 'draft'" class="flex gap-2">
                    <Link :href="`/owner/reactivation/${campaign.id}/edit`" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600">Edit</Link>
                    <button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600" @click="cancel">Cancel</button>
                    <button :disabled="!canSend" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45" @click="send">Send message</button>
                </div>
                <div v-else-if="campaign.delivery_mode === 'automated'" class="flex gap-2"><Link :href="`/owner/reactivation/${campaign.id}/edit`" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600">Edit</Link><button class="rounded-xl border border-court-300 bg-white px-4 py-2.5 text-sm font-semibold text-court-800" @click="toggleAutomation">{{ campaign.automation_enabled ? 'Pause automation' : 'Resume automation' }}</button></div>
            </div>
            <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Player message</p><p class="mt-3 text-lg leading-7 text-slate-700">{{ campaign.message }}</p><div v-if="campaign.status === 'draft'" class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">Saving did not contact anyone. When you send, FinACourt only includes past players from your venue who agreed to receive messages and have not been contacted too recently.</div></section>
            <section v-if="campaign.offer" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950"><p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Exclusive customer offer</p><p class="mt-1 text-lg font-semibold">{{ campaign.offer.label }}</p><p class="mt-1">Valid for {{ campaign.offer.valid_days }} days<span v-if="campaign.offer.minimum_spend"> · Minimum court subtotal ₱{{ campaign.offer.minimum_spend }}</span><span v-if="campaign.offer.max_redemptions"> · Up to {{ campaign.offer.max_redemptions }} redemptions</span>. Each eligible recipient receives a private, one-time offer attached to their FinACourt account.</p></section>
            <section v-if="campaign.delivery_mode === 'automated'" class="mt-5 rounded-2xl border border-court-200 bg-court-50 p-5 text-sm leading-6 text-court-950"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Automated win-back safeguards</p><p class="mt-1">This campaign checks hourly for customers inactive at <strong>{{ campaign.venue }}</strong>. A player is excluded when they have a current or future booking there, have not opted into marketing, are inside the contact cooldown, or already received this campaign for the same inactivity cycle.</p></section>

            <section v-if="eligibility" class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Delivery check</p>
                <h3 class="mt-1 text-xl font-semibold">{{ campaign.delivery_mode === 'automated' ? 'Estimated eligible audience now' : 'Who can receive this message now' }}</h3>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div v-for="metric in [['Players found', eligibility.audience], ['Eligible', eligibility.eligible], ['By email', eligibility.email], ['In-app', eligibility.in_app], ['Not eligible', eligibility.suppressed]]" :key="metric[0]" class="rounded-xl bg-slate-50 p-4 text-center">
                        <strong class="text-2xl">{{ metric[1] }}</strong>
                        <p class="mt-1 text-xs text-slate-400">{{ metric[0] }}</p>
                    </div>
                </div>

                <div v-if="eligibility.audience === 0" class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    No past players currently match this venue, sport, and player group. Nothing can be sent yet.
                </div>
                <div v-else-if="eligibility.eligible === 0" class="mt-5 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                    <strong class="block">Nothing will be sent yet.</strong>
                    <span v-if="eligibility.suppression_reasons.marketing_opt_out">{{ eligibility.suppression_reasons.marketing_opt_out }} {{ eligibility.suppression_reasons.marketing_opt_out === 1 ? 'player has' : 'players have' }} not enabled comeback messages in FinACourt.</span>
                    <span v-if="eligibility.suppression_reasons.marketing_opt_out && eligibility.suppression_reasons.frequency_cooldown"> </span>
                    <span v-if="eligibility.suppression_reasons.frequency_cooldown">{{ eligibility.suppression_reasons.frequency_cooldown }} {{ eligibility.suppression_reasons.frequency_cooldown === 1 ? 'player is' : 'players are' }} still inside the contact cooldown.</span>
                </div>
                <div v-else-if="eligibility.email === 0" class="mt-5 rounded-xl bg-sky-50 p-4 text-sm leading-6 text-sky-900">
                    No eligible player has enabled email comeback messages. The message will use in-app delivery only.
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">{{ campaign.delivery_mode === 'automated' ? 'This estimate is recalculated when the hourly job runs. Players who book again before then are excluded.' : 'Players control these permissions from My bookings → Game alerts. Court owners cannot enable marketing email on a player’s behalf.' }}</p>
            </section>

            <section v-if="campaign.status === 'sent' || campaign.status === 'active'" class="mt-7 grid gap-3 sm:grid-cols-5">
                <div v-for="metric in [['Players found', campaign.audience], ['Queued', campaign.sent], ['Delivered', campaign.delivered], ['Failed', campaign.failed], ['Opened link', campaign.clicks]]" :key="metric[0]" class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm"><strong class="text-2xl">{{ metric[1] }}</strong><p class="mt-1 text-xs text-slate-400">{{ metric[0] }}</p></div>
            </section>

            <section v-if="(campaign.status === 'sent' || campaign.status === 'active') && campaign.suppressed > 0" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                <h3 class="font-semibold">Why {{ campaign.suppressed === 1 ? 'a player was' : 'players were' }} not sent this message</h3>
                <ul class="mt-2 space-y-1">
                    <li v-if="campaign.suppression_reasons.marketing_opt_out">{{ campaign.suppression_reasons.marketing_opt_out }} {{ campaign.suppression_reasons.marketing_opt_out === 1 ? 'player has' : 'players have' }} not enabled comeback messages in FinACourt, so no email or in-app message was attempted.</li>
                    <li v-if="campaign.suppression_reasons.frequency_cooldown">{{ campaign.suppression_reasons.frequency_cooldown }} {{ campaign.suppression_reasons.frequency_cooldown === 1 ? 'player was' : 'players were' }} contacted too recently and protected by the contact cooldown.</li>
                    <li v-if="campaign.suppression_reasons.other">{{ campaign.suppression_reasons.other }} {{ campaign.suppression_reasons.other === 1 ? 'player was' : 'players were' }} excluded by another delivery safeguard.</li>
                </ul>
                <p class="mt-3 text-xs text-amber-800">Player permissions are private. A player can enable email from My bookings → Game alerts; a court owner cannot change this setting.</p>
            </section>
        </div>
    </OwnerLayout>
</template>
