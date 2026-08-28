<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';

const props = defineProps({ organizations: Array, selectedOrganization: Object, report: Object });
const form = reactive({ organization: props.selectedOrganization?.id || '' });
const label = (key) => String(key).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
function inspect() { router.get('/platform/growth', form, { preserveState: true, replace: true }); }
</script>

<template>
    <Head title="Growth recommendation observability" />
    <PlatformLayout>
        <div class="space-y-7">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div><p class="eyebrow">Rule observability</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Growth recommendation debugger</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Inspect the deterministic rule, current evidence, action context, staleness, and owner suppression state for one organization. No recommendation is executed here.</p></div>
                <form class="app-card flex min-w-80 items-end gap-3 p-3" @submit.prevent="inspect"><label class="flex-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Organization<AppSelect v-model="form.organization" :options="organizations" option-value="id" option-label="name" class="mt-1" required /></label><button class="rounded-lg bg-court-700 px-4 py-3 text-sm font-semibold text-white">Inspect</button></form>
            </div>

            <section v-if="!selectedOrganization" class="app-card px-6 py-16 text-center"><h2 class="text-xl font-semibold">Choose an organization</h2><p class="mt-2 text-sm text-slate-500">The platform intentionally calculates one tenant at a time to keep the debugging query bounded.</p></section>

            <template v-else>
                <section class="rounded-2xl bg-slate-950 p-6 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-court-300">{{ selectedOrganization.name }}</p><div class="mt-3 flex flex-wrap items-end justify-between gap-4"><div><h2 class="text-2xl font-semibold">{{ report.active.length }} active · {{ report.suppressed.length }} suppressed</h2><p class="mt-2 text-sm text-slate-300">Calculated {{ new Date(report.calculated_at).toLocaleString() }} from a {{ report.lookback_days }}-day evidence window.</p></div><span class="rounded-full bg-white/10 px-3 py-1.5 text-xs">Rule-based only</span></div></section>

                <section v-if="report.active.length || report.suppressed.length" class="grid gap-5 xl:grid-cols-2">
                    <article v-for="recommendation in [...report.active, ...report.suppressed]" :key="recommendation.key" class="app-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5"><div class="flex flex-wrap gap-2 text-[11px]"><span class="rounded-full bg-slate-950 px-2.5 py-1 font-semibold text-white">{{ recommendation.rule }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1">{{ recommendation.priority }}</span><span class="rounded-full bg-slate-100 px-2.5 py-1">{{ recommendation.state }}</span></div><h2 class="mt-4 text-lg font-semibold">{{ recommendation.title }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">{{ recommendation.explanation }}</p></div>
                        <dl class="grid gap-px bg-slate-100 sm:grid-cols-2"><div v-for="(value, key) in recommendation.evidence" :key="key" class="bg-white px-5 py-3"><dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ label(key) }}</dt><dd class="mt-1 break-words text-sm font-medium text-slate-800">{{ value }}</dd></div></dl>
                        <div class="space-y-1 border-t border-slate-100 bg-slate-50 px-5 py-4 text-xs text-slate-500"><p><strong>Action:</strong> {{ recommendation.suggested_action.label }} · {{ recommendation.suggested_action.url }}</p><p><strong>Key:</strong> <code class="break-all">{{ recommendation.key }}</code></p><p><strong>Expires:</strong> {{ new Date(recommendation.expires_at).toLocaleString() }} · stale={{ recommendation.is_stale }}</p></div>
                    </article>
                </section>
                <section v-else class="app-card px-6 py-14 text-center"><h2 class="text-xl font-semibold">No rule has enough evidence</h2><p class="mt-2 text-sm text-slate-500">This is a valid safe state; the platform does not manufacture recommendations.</p></section>
            </template>
        </div>
    </PlatformLayout>
</template>
