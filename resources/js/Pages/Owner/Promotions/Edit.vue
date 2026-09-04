<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PromotionForm from '../../../Components/PromotionForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ promotion: Object, venues: Array, types: Array, discountTypes: Array, weekdays: Array, goals: Array, statuses: Array, opportunities: Array, serviceFee: Object });
</script>

<template>
    <Head :title="`Edit ${promotion.title}`" />
    <OwnerLayout><div class="mx-auto max-w-6xl"><Link :href="`/owner/promotions/${promotion.id}`" class="text-sm font-semibold text-court-700">← Promotion preview</Link><p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Guided setup</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">Edit promotion</h2><p class="mt-2 text-sm text-slate-500">Review each step before saving. Existing bookings keep the price recorded when they were made.</p><PromotionForm class="mt-7" v-bind="$props" /></div></OwnerLayout>
</template>
