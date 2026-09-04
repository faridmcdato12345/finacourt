<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PromotionForm from '../../../Components/PromotionForm.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

defineProps({ venues: Array, types: Array, discountTypes: Array, weekdays: Array, goals: Array, statuses: Array, opportunities: Array, defaults: Object, serviceFee: Object });
</script>

<template>
    <Head title="Create deal" />
    <OwnerLayout><div class="mx-auto max-w-6xl"><Link href="/owner/promotions" class="text-sm font-semibold text-court-700">← Promotions</Link><p class="mt-5 text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Guided setup</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">Create a promotion</h2><p class="mt-2 text-sm text-slate-500">Choose a strategy, select eligible court times, set the offer, and review it before publishing.</p><PromotionForm class="mt-7" v-bind="$props" /></div></OwnerLayout>
</template>
