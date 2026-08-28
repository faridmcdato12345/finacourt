<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PartnerLayout from '../../../Layouts/PartnerLayout.vue';

defineProps({ leads: Object, can_create: Boolean });
</script>

<template>
    <Head title="Sales leads" />
    <PartnerLayout>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Protected acquisition work</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Leads</h1><p class="mt-3 text-sm text-slate-600">Only leads assigned to your account are shown.</p></div><Link v-if="can_create" href="/partner/leads/create" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Register lead</Link></div>
        <section class="app-card mt-7 overflow-hidden"><div class="divide-y divide-slate-100"><Link v-for="lead in leads.data" :key="lead.id" :href="`/partner/leads/${lead.id}`" class="grid gap-3 p-5 hover:bg-slate-50 sm:grid-cols-[1fr_auto_auto] sm:items-center"><div><h2 class="font-semibold text-slate-950">{{ lead.business_name }}</h2><p class="mt-1 text-sm text-slate-500">{{ lead.city }} · registered {{ lead.created_at }}</p></div><span class="text-sm text-slate-600">{{ lead.status_label }}</span><span :class="['rounded-full px-3 py-1 text-xs font-semibold', lead.conflict_status === 'disputed' ? 'bg-amber-100 text-amber-800' : 'bg-court-50 text-court-800']">{{ lead.conflict_status }}</span></Link><p v-if="!leads.data.length" class="p-12 text-center text-sm text-slate-500">No leads registered.</p></div></section>
    </PartnerLayout>
</template>
