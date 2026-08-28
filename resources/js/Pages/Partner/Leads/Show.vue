<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import PartnerLayout from '../../../Layouts/PartnerLayout.vue';

const props = defineProps({ lead: Object });
const onboarding = useForm({ notes: props.lead.notes || '', onboarding_data: { venue_name: '', address: '', city: '', province: '', phone: '', sports: '', courts: '', hours: '', pricing: '', ...(props.lead.onboarding_data || {}) } });
const lifecycle = useForm({ status: props.lead.next_statuses[0]?.value || '' });
</script>

<template>
    <Head :title="lead.business_name" />
    <PartnerLayout>
        <div class="mx-auto max-w-5xl space-y-6"><Link href="/partner/leads" class="text-sm font-semibold text-court-700">← Back to leads</Link><section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">{{ lead.city }}</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ lead.business_name }}</h1><p class="mt-2 text-sm text-slate-500">{{ lead.contact_person }} · {{ lead.contact_method }} · {{ lead.contact_value }}</p></div><div class="flex gap-2"><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold">{{ lead.status_label }}</span><span :class="['rounded-full px-3 py-1.5 text-xs font-semibold', lead.conflict_status === 'disputed' ? 'bg-amber-100 text-amber-800' : 'bg-court-50 text-court-800']">{{ lead.conflict_status }}</span></div></section>
            <div v-if="lead.conflict_status === 'disputed'" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">Another protected or activated lead matches this record. No lifecycle or onboarding work is allowed until platform review.</div>
            <form v-if="lead.can_edit" class="app-card grid gap-5 p-6 sm:grid-cols-2" @submit.prevent="onboarding.put(`/partner/leads/${lead.id}`)"><div class="sm:col-span-2"><h2 class="text-xl font-semibold">Assisted onboarding notes</h2><p class="mt-2 text-sm text-slate-500">These are draft facts only. A verified owner must create and own the final organization.</p></div><label v-for="field in ['venue_name','address','city','province','phone','sports','courts','hours','pricing']" :key="field"><span class="text-sm font-medium capitalize">{{ field.replace('_',' ') }}</span><input v-model="onboarding.onboarding_data[field]" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label><label class="sm:col-span-2"><span class="text-sm font-medium">Internal notes</span><textarea v-model="onboarding.notes" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3"></textarea></label><div class="sm:col-span-2"><button class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Save onboarding draft</button></div></form>
            <form v-if="lead.can_edit && lead.next_statuses.length" class="app-card flex flex-col gap-4 p-6 sm:flex-row sm:items-end" @submit.prevent="lifecycle.patch(`/partner/leads/${lead.id}/status`)"><label class="flex-1"><span class="text-sm font-medium">Next lifecycle step</span><AppSelect v-model="lifecycle.status" :options="lead.next_statuses" class="mt-1.5" /></label><button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Update stage</button></form>
        </div>
    </PartnerLayout>
</template>
