<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineProps({
    user: { type: Object, required: true },
    claimInvitation: { type: Boolean, default: false },
});

const form = useForm({ organization_name: '' });
</script>

<template>
    <Head title="Finish setting up your court business" />
    <GuestLayout>
        <div class="w-full">
            <p class="text-sm font-semibold text-court-700">One last step</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Name your court business</h1>
            <p class="mt-3 leading-7 text-slate-600">You are signed in as <strong>{{ user.email }}</strong>. Tell us what to call your court business so we can prepare your owner pages.</p>

            <p v-if="claimInvitation" class="mt-5 rounded-xl border border-court-200 bg-court-50 p-4 text-sm leading-6 text-court-900">Your private venue invitation is waiting. Complete this owner setup and FinACourt will return you there automatically.</p>

            <form class="mt-8 space-y-5" @submit.prevent="form.post('/owner/social/setup')">
                <div>
                    <label for="organization_name" class="mb-2 block text-sm font-medium text-slate-800">Court business name</label>
                    <input id="organization_name" v-model="form.organization_name" required autofocus autocomplete="organization" placeholder="Example: Marawi Sports Center" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-950 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.organization_name" class="mt-2 text-sm text-red-600">{{ form.errors.organization_name }}</p>
                </div>
                <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Getting things ready…' : 'Open my owner pages' }}</button>
            </form>
        </div>
    </GuestLayout>
</template>
