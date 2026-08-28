<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PromotionForm from '../../../Components/PromotionForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ venues: Array, types: Array, discountTypes: Array, weekdays: Array, goals: Array, statuses: Array, opportunities: Array, defaults: Object });
</script>

<template>
    <Head title="Create promotion" />
    <OwnerLayout><div class="mx-auto max-w-5xl"><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">← Promotions</Link><h2 class="mt-4 text-3xl font-semibold tracking-tight">Create a campaign</h2><p class="mt-2 text-sm text-slate-500">Choose a business goal, approve real inventory, and keep final pricing server-authoritative.</p><PromotionForm class="mt-7" v-bind="$props" /></div></OwnerLayout>
</template>
