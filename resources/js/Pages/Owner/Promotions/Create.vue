<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PromotionForm from '../../../Components/PromotionForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ venues: Array, types: Array, discountTypes: Array, weekdays: Array, goals: Array, statuses: Array, opportunities: Array, defaults: Object });
</script>

<template>
    <Head title="Create deal" />
    <OwnerLayout><div class="mx-auto max-w-5xl"><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">← Deals</Link><h2 class="mt-4 text-3xl font-semibold tracking-tight">Create a deal</h2><p class="mt-2 text-sm text-slate-500">Choose what the deal is for, when it should run, and whether it changes the price.</p><PromotionForm class="mt-7" v-bind="$props" /></div></OwnerLayout>
</template>
