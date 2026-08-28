<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({ partners: Array, leads: Array, rules: Array, commissions: Array, payouts: Array, organizations: Array, audit: Array });
const partnerForm = useForm({ email: '', payout_details: '' });
const ruleForm = useForm({ name: '', trigger: 'venue_activation', fixed_amount: '', currency: 'PHP', is_active: true, effective_from: '', effective_until: '' });
const adjustment = useForm({ partner_id: '', amount: '', reason: '' });
const payout = useForm({ partner_id: '', period_started_at: '', period_ended_at: '', note: '' });
const partnerOptions = props.partners.map((partner) => ({ value: partner.id, label: `${partner.name} · ${partner.referral_code}` }));

function setPartnerStatus(partner, status) {
    const reason = status === 'suspended' ? window.prompt('Required suspension reason:') : null;
    if (status === 'suspended' && !reason) return;
    router.patch(`/platform/sales/partners/${partner.id}`, { status, reason }, { preserveScroll: true });
}

function transitionLead(lead) {
    const status = window.prompt('Next status: contacted, demo_scheduled, interested, onboarding, won, lost, or expired');
    if (status) router.patch(`/platform/sales/leads/${lead.id}/status`, { status }, { preserveScroll: true });
}

function activateLead(lead) {
    const organization_id = window.prompt('Organization ID owned by the real court owner:');
    if (!organization_id) return;
    const venue_id = window.prompt('Venue ID in that organization:');
    const owner_user_id = window.prompt('Verified owner user ID:');
    if (venue_id && owner_user_id) router.post(`/platform/sales/leads/${lead.id}/activate`, { organization_id, venue_id, owner_user_id }, { preserveScroll: true });
}

function overrideLead(lead) {
    const partner_id = window.prompt('Assign active partner profile ID:');
    const reason = window.prompt('Required dispute/override reason:');
    if (partner_id && reason) router.post(`/platform/sales/leads/${lead.id}/override`, { partner_id, reason }, { preserveScroll: true });
}

function approveCommission(entry) { router.post(`/platform/sales/commissions/${entry.id}/approve`, {}, { preserveScroll: true }); }
function reverseCommission(entry) {
    const reason = window.prompt('Required reversal reason:');
    if (reason) router.post(`/platform/sales/commissions/${entry.id}/reverse`, { reason }, { preserveScroll: true });
}
function payoutAction(item, action) {
    const payload = action === 'pay' ? { reference: window.prompt('External payout reference:'), note: '' } : action === 'cancel' ? { reason: window.prompt('Cancellation reason:') } : {};
    if ((action === 'pay' && !payload.reference) || (action === 'cancel' && !payload.reason)) return;
    router.post(`/platform/sales/payouts/${item.id}/${action}`, payload, { preserveScroll: true });
}
</script>

<template>
    <Head title="Sales partner administration" />
    <PlatformLayout>
        <div class="space-y-8">
            <section><p class="eyebrow">Acquisition governance</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Sales partners, leads, and commissions</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Partner access is isolated from tenant operations. A commission exists only after verified venue activation under an explicit active rule, then requires approval and manual payout evidence.</p></section>

            <section class="grid gap-5 xl:grid-cols-2">
                <form class="app-card p-6" @submit.prevent="partnerForm.post('/platform/sales/partners', { onSuccess: () => partnerForm.reset() })"><h2 class="text-lg font-semibold">Activate an existing user as partner</h2><p class="mt-2 text-xs leading-5 text-slate-500">The user must already own their login and cannot be a tenant member or platform admin.</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-medium">User email</span><input v-model="partnerForm.email" type="email" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /><span v-if="partnerForm.errors.email" class="mt-1 block text-xs text-red-600">{{ partnerForm.errors.email }}</span></label><label><span class="text-sm font-medium">Manual payout instructions (encrypted)</span><input v-model="partnerForm.payout_details" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label></div><button class="mt-5 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Activate partner</button></form>
                <form class="app-card p-6" @submit.prevent="ruleForm.post('/platform/sales/commission-rules', { onSuccess: () => ruleForm.reset('name','fixed_amount','effective_from','effective_until') })"><h2 class="text-lg font-semibold">Commission rule</h2><p class="mt-2 text-xs leading-5 text-slate-500">V1 supports only fixed commission after a platform-verified venue activation. No percentage is inferred from owner booking revenue.</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-medium">Rule name</span><input v-model="ruleForm.name" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label><span class="text-sm font-medium">Fixed amount (PHP)</span><input v-model="ruleForm.fixed_amount" inputmode="decimal" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label><span class="text-sm font-medium">Effective from</span><input v-model="ruleForm.effective_from" type="date" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label><span class="text-sm font-medium">Effective until</span><input v-model="ruleForm.effective_until" type="date" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label></div><label class="mt-4 flex items-center gap-2 text-sm"><input v-model="ruleForm.is_active" type="checkbox" /> Active immediately</label><button class="mt-5 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Save rule</button></form>
            </section>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Partner accounts</h2></div><div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th class="px-5 py-3">Partner</th><th>Referral</th><th>Leads</th><th>Activated</th><th>Pending</th><th>Available</th><th>Status</th><th>Action</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="partner in partners" :key="partner.id"><td class="px-5 py-4"><p class="font-medium">{{ partner.name }}</p><p class="text-xs text-slate-400">#{{ partner.id }} · {{ partner.email }}</p></td><td>{{ partner.referral_code }}</td><td>{{ partner.leads }}</td><td>{{ partner.activated_venues }}</td><td>₱{{ partner.pending }}</td><td>₱{{ partner.available }}</td><td>{{ partner.status }}</td><td><button v-if="partner.status === 'active'" class="text-xs font-semibold text-red-700" @click="setPartnerStatus(partner,'suspended')">Suspend</button><button v-else class="text-xs font-semibold text-court-700" @click="setPartnerStatus(partner,'active')">Activate</button></td></tr></tbody></table></div></section>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Lead lifecycle and disputes</h2><p class="mt-1 text-xs text-slate-500">Only an admin can bind onboarding work to a real owner, organization, and venue.</p></div><div class="overflow-x-auto"><table class="w-full min-w-[1050px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th class="px-5 py-3">Lead</th><th>Partner</th><th>Stage</th><th>Conflict</th><th>Protection</th><th>Activation</th><th>Actions</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="lead in leads" :key="lead.id"><td class="px-5 py-4"><p class="font-medium">{{ lead.business_name }}</p><p class="text-xs text-slate-400">#{{ lead.id }} · {{ lead.city }}</p></td><td>{{ lead.partner_name }} (#{{ lead.partner_id }})</td><td>{{ lead.status_label }}</td><td>{{ lead.conflict_status }}<span v-if="lead.duplicate_of_lead_id"> of #{{ lead.duplicate_of_lead_id }}</span></td><td>{{ lead.protection_expires_at || 'none' }}</td><td>{{ lead.venue || 'Not verified' }}</td><td><div class="flex gap-3"><button class="text-xs font-semibold text-court-700" @click="transitionLead(lead)">Stage</button><button v-if="lead.status === 'onboarding' && lead.conflict_status !== 'disputed'" class="text-xs font-semibold text-blue-700" @click="activateLead(lead)">Verify activation</button><button v-if="lead.conflict_status === 'disputed'" class="text-xs font-semibold text-amber-700" @click="overrideLead(lead)">Resolve</button></div></td></tr></tbody></table></div></section>

            <section class="grid gap-5 xl:grid-cols-2"><div class="app-card p-6"><h2 class="text-lg font-semibold">Current rules</h2><div class="mt-4 divide-y divide-slate-100"><div v-for="rule in rules" :key="rule.id" class="flex items-center justify-between gap-4 py-4"><div><p class="font-medium">{{ rule.name }}</p><p class="mt-1 text-xs text-slate-500">{{ rule.trigger_label }} · ₱{{ rule.amount }} · {{ rule.effective_from || 'open' }} to {{ rule.effective_until || 'open' }}</p></div><button class="text-xs font-semibold text-court-700" @click="router.patch(`/platform/sales/commission-rules/${rule.id}`, {is_active: !rule.is_active}, {preserveScroll:true})">{{ rule.is_active ? 'Pause' : 'Activate' }}</button></div><p v-if="!rules.length" class="py-8 text-center text-sm text-slate-500">No commission rule configured; activation awards nothing.</p></div></div>
                <form class="app-card p-6" @submit.prevent="adjustment.post('/platform/sales/commissions/adjust', { onSuccess: () => adjustment.reset() })"><h2 class="text-lg font-semibold">Append-only adjustment</h2><p class="mt-2 text-xs leading-5 text-slate-500">Use a positive correction or negative recovery. Original ledger facts are not overwritten.</p><label class="mt-4 block"><span class="text-sm font-medium">Partner</span><AppSelect v-model="adjustment.partner_id" :options="partnerOptions" class="mt-1.5" /></label><div class="mt-4 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-medium">Amount</span><input v-model="adjustment.amount" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label><span class="text-sm font-medium">Reason</span><input v-model="adjustment.reason" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label></div><button class="mt-5 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Create pending adjustment</button></form></section>

            <section class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Commission ledger</h2></div><div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th class="px-5 py-3">Entry</th><th>Partner</th><th>Source</th><th>Amount</th><th>Status</th><th>Reason</th><th>Actions</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="entry in commissions" :key="entry.id"><td class="px-5 py-4">#{{ entry.id }}<p class="text-xs text-slate-400">{{ entry.created_at }}</p></td><td>{{ entry.partner }}</td><td>{{ entry.source }}<p class="text-xs text-slate-400">{{ entry.lead }}</p></td><td>₱{{ entry.amount }}</td><td>{{ entry.status }}</td><td>{{ entry.reason }}</td><td><div class="flex gap-3"><button v-if="entry.status === 'pending'" class="text-xs font-semibold text-court-700" @click="approveCommission(entry)">Approve</button><button v-if="entry.status !== 'reversed'" class="text-xs font-semibold text-red-700" @click="reverseCommission(entry)">Reverse</button></div></td></tr></tbody></table></div></section>

            <section class="grid gap-5 xl:grid-cols-[0.75fr_1.25fr]"><form class="app-card p-6" @submit.prevent="payout.post('/platform/sales/payouts', { onSuccess: () => payout.reset() })"><h2 class="text-lg font-semibold">Create manual payout batch</h2><label class="mt-4 block"><span class="text-sm font-medium">Partner</span><AppSelect v-model="payout.partner_id" :options="partnerOptions" class="mt-1.5" /></label><div class="mt-4 grid gap-4 sm:grid-cols-2"><label><span class="text-sm font-medium">Period start</span><input v-model="payout.period_started_at" type="date" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label><span class="text-sm font-medium">Period end</span><input v-model="payout.period_ended_at" type="date" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label></div><label class="mt-4 block"><span class="text-sm font-medium">Note</span><input v-model="payout.note" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><button class="mt-5 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Create payout</button></form><div class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Payout records</h2></div><div class="divide-y divide-slate-100"><div v-for="item in payouts" :key="item.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-medium">{{ item.partner }} · ₱{{ item.amount }}</p><p class="mt-1 text-xs text-slate-500">{{ item.period_started_at }} – {{ item.period_ended_at }} · {{ item.status }} <span v-if="item.reference">· {{ item.reference }}</span></p></div><div class="flex gap-3"><button v-if="item.status === 'pending'" class="text-xs font-semibold text-court-700" @click="payoutAction(item,'approve')">Approve</button><button v-if="item.status === 'approved'" class="text-xs font-semibold text-blue-700" @click="payoutAction(item,'pay')">Mark paid</button><button v-if="['pending','approved'].includes(item.status)" class="text-xs font-semibold text-red-700" @click="payoutAction(item,'cancel')">Cancel</button></div></div><p v-if="!payouts.length" class="p-8 text-center text-sm text-slate-500">No payout batches.</p></div></div></section>

            <section class="app-card p-6"><h2 class="text-lg font-semibold">Recent audit history</h2><div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-3"><div v-for="event in audit" :key="event.id" class="rounded-xl bg-slate-50 px-4 py-3 text-xs"><p class="font-semibold text-slate-700">{{ event.action }}</p><p class="mt-1 text-slate-400">#{{ event.id }} · partner {{ event.partner_id || '—' }} · lead {{ event.lead_id || '—' }}</p></div></div></section>
        </div>
    </PlatformLayout>
</template>
