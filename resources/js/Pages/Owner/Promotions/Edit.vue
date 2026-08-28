<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PromotionForm from '../../../Components/PromotionForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ promotion: Object, venues: Array, types: Array, discountTypes: Array, weekdays: Array, goals: Array, statuses: Array, opportunities: Array });
</script>

<template>
    <Head :title="`Edit ${promotion.title}`" />
    <OwnerLayout><div class="mx-auto max-w-5xl"><Link :href="`/owner/promotions/${promotion.id}`" class="text-sm font-semibold text-court-700">← Deal preview</Link><h2 class="mt-4 text-3xl font-semibold tracking-tight">Edit deal</h2><p class="mt-2 text-sm text-slate-500">Changes affect new bookings only. Old bookings keep the price shown when they were made.</p><PromotionForm class="mt-7" v-bind="$props" /></div></OwnerLayout>
</template>
