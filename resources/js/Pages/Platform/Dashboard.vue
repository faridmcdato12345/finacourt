<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '../../Layouts/PlatformLayout.vue';
</script>

<template>
    <Head title="Platform administration" />
    <PlatformLayout>
        <div class="max-w-3xl">
            <p class="text-sm font-semibold text-court-700">Platform access confirmed</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Administration foundation</h1>
            <p class="mt-4 leading-7 text-slate-600">This protected shell is reserved for explicitly designated platform administrators. Cross-organization operations will be added only with resource-specific policies in later phases.</p>
            <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">No tenant management, moderation, metrics, or domain operations are included in Phase 1.</div>
        </div>
    </PlatformLayout>
</template>
