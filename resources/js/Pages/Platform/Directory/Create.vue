<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DirectoryListingForm from '../../../Components/DirectoryListingForm.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
defineProps({ sports: Array, sourceTypes: Array, weekdays: Array, locationParents: Array });
</script>
<template><Head title="Add a venue" /><PlatformLayout><div class="mx-auto max-w-5xl"><Link href="/platform/directory" class="text-sm font-semibold text-court-700">← Public venue guide</Link><div class="my-6"><p class="eyebrow">Help players discover a venue</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">Add a venue</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Start with trustworthy public details. This creates a draft only—there is no owner account, live availability, pricing, reviews, or booking until the real owner joins and finishes setup.</p></div><DirectoryListingForm :sports="sports" :source-types="sourceTypes" :weekdays="weekdays" :location-parents="locationParents" /></div></PlatformLayout></template>
