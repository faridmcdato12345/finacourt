<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PartnerLayout from '../../Layouts/PartnerLayout.vue';

defineProps({ partner: Object, metrics: Object, leads: Array, payouts: Array });
</script>

<template>
    <Head title="Sales partner overview" />
    <PartnerLayout>
        <div class="space-y-7">
            <section class="grid gap-6 lg:grid-cols-[1.35fr_0.65fr]">
                <div>
                    <p class="eyebrow">Authorized acquisition partner</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Leads, activations, and auditable earnings</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Register legitimate venue leads, assist with setup, and follow platform-approved commission milestones. You never receive tenant customer or payment access.</p>
                </div>
                <div :class="['rounded-2xl border p-5', partner.status === 'active' ? 'border-court-200 bg-court-50' : 'border-amber-200 bg-amber-50']">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Account status</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950">{{ partner.status_label }}</p>
                    <p v-if="partner.suspension_reason" class="mt-2 text-sm text-amber-800">{{ partner.suspension_reason }}</p>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article v-for="(value, key) in metrics" :key="key" class="app-card p-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ key.replaceAll('_', ' ') }}</p><p class="mt-2 text-2xl font-semibold text-slate-950">{{ ['pending', 'available', 'paid'].includes(key) ? `₱${value}` : value }}</p></article>
            </section>

            <section class="app-card overflow-hidden">
                <div class="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your stable referral</p><h2 class="mt-2 text-xl font-semibold">{{ partner.referral_code }}</h2><p class="mt-2 break-all text-sm text-slate-500">{{ partner.referral_url }}</p><p class="mt-3 text-xs leading-5 text-slate-400">This server-issued route records trusted partner context. Copying a browser query parameter does not create commission evidence.</p></div>
                    <div class="flex items-center gap-3"><a :href="partner.qr_url" target="_blank" class="rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Open QR</a><a :href="partner.referral_url" target="_blank" class="rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white">Open referral page</a></div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="app-card overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="font-semibold">Recent leads</h2><p class="mt-1 text-xs text-slate-500">Protection is time-limited and disputes are admin-reviewed.</p></div><Link v-if="partner.status === 'active'" href="/partner/leads/create" class="rounded-lg bg-court-700 px-3 py-2 text-xs font-semibold text-white">Register lead</Link></div><div class="divide-y divide-slate-100"><Link v-for="lead in leads" :key="lead.id" :href="`/partner/leads/${lead.id}`" class="flex items-center justify-between gap-4 p-5 hover:bg-slate-50"><div><p class="font-medium text-slate-900">{{ lead.business_name }}</p><p class="mt-1 text-xs text-slate-500">{{ lead.city }} · {{ lead.status }}</p></div><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', lead.conflict === 'disputed' ? 'bg-amber-100 text-amber-800' : 'bg-court-50 text-court-800']">{{ lead.conflict }}</span></Link><p v-if="!leads.length" class="p-8 text-center text-sm text-slate-500">No leads yet.</p></div></div>
                <div class="app-card overflow-hidden"><div class="border-b border-slate-100 p-5"><h2 class="font-semibold">Payout history</h2><p class="mt-1 text-xs text-slate-500">Payouts are approved and sent manually outside this application.</p></div><div class="divide-y divide-slate-100"><div v-for="payout in payouts" :key="payout.id" class="flex items-center justify-between gap-4 p-5"><div><p class="font-medium">₱{{ payout.amount }}</p><p class="mt-1 text-xs text-slate-500">{{ payout.period }}</p></div><div class="text-right"><p class="text-xs font-semibold uppercase text-slate-600">{{ payout.status }}</p><p v-if="payout.reference" class="mt-1 text-xs text-slate-400">{{ payout.reference }}</p></div></div><p v-if="!payouts.length" class="p-8 text-center text-sm text-slate-500">No payout records yet.</p></div></div>
            </section>
        </div>
    </PartnerLayout>
</template>
