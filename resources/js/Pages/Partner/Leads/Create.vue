<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import PartnerLayout from '../../../Layouts/PartnerLayout.vue';

const form = useForm({ business_name: '', contact_person: '', contact_method: 'email', contact_value: '', city: '', lead_source: 'field_outreach', notes: '' });
</script>

<template>
    <Head title="Register sales lead" />
    <PartnerLayout>
        <div class="mx-auto max-w-3xl"><Link href="/partner/leads" class="text-sm font-semibold text-court-700">← Back to leads</Link><h1 class="mt-4 text-3xl font-semibold tracking-tight">Register a legitimate venue lead</h1><p class="mt-3 text-sm leading-6 text-slate-600">Use the minimum contact information needed for real outreach. Accounts are never created automatically.</p>
            <form class="app-card mt-7 grid gap-5 p-6 sm:grid-cols-2" @submit.prevent="form.post('/partner/leads')">
                <label class="sm:col-span-2"><span class="text-sm font-medium">Venue or business name</span><input v-model="form.business_name" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /><span v-if="form.errors.business_name" class="mt-1 text-xs text-red-600">{{ form.errors.business_name }}</span></label>
                <label><span class="text-sm font-medium">Contact person</span><input v-model="form.contact_person" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label>
                <label><span class="text-sm font-medium">City / area</span><input v-model="form.city" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label>
                <label><span class="text-sm font-medium">Contact method</span><AppSelect v-model="form.contact_method" :options="[{value:'email',label:'Email'},{value:'phone',label:'Phone'},{value:'messenger',label:'Messenger'},{value:'other',label:'Other'}]" class="mt-1.5" /></label>
                <label><span class="text-sm font-medium">Contact value</span><input v-model="form.contact_value" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /><span v-if="form.errors.contact_value" class="mt-1 text-xs text-red-600">{{ form.errors.contact_value }}</span></label>
                <label><span class="text-sm font-medium">Lead source</span><input v-model="form.lead_source" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3" /></label>
                <label class="sm:col-span-2"><span class="text-sm font-medium">Notes</span><textarea v-model="form.notes" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-3"></textarea></label>
                <div class="sm:col-span-2"><button :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">Register lead</button></div>
            </form>
        </div>
    </PartnerLayout>
</template>
