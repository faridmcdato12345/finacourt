<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '../../Layouts/PlatformLayout.vue';

defineProps({
    googleBusinessProfiles: {
        type: Object,
        required: true,
    },
});

const statusLabels = {
    not_connected: 'Not connected',
    pending_discovery: 'Checking Google venues',
    needs_confirmation: 'Waiting for owner choice',
    connected: 'Connected',
    no_match: 'No managed match found',
    reconnect_required: 'Needs reconnecting',
    disconnected: 'Disconnected',
};

function formatDate(value) {
    if (!value) return 'Not checked yet';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Platform administration" />
    <PlatformLayout>
        <div>
            <p class="text-sm font-semibold text-court-700">Platform overview</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">FinACourt administration</h1>
            <p class="mt-4 max-w-3xl leading-7 text-slate-600">Check platform-wide services that need attention without opening an owner’s private workspace.</p>

            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-court-700">Google Business Profile</p>
                        <h2 class="mt-1 text-xl font-semibold text-slate-950">Owner connection health</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">This is read-only support information. Google access tokens and account resource IDs are never shown here.</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="googleBusinessProfiles.enabled ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                        {{ googleBusinessProfiles.enabled ? 'Integration enabled' : 'Integration disabled' }}
                    </span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(label, status) in statusLabels" :key="status" class="rounded-xl bg-slate-50 p-4">
                        <p class="text-sm text-slate-600">{{ label }}</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-950">{{ googleBusinessProfiles.counts[status] ?? 0 }}</p>
                    </div>
                </div>

                <div class="mt-6 overflow-hidden rounded-xl border border-slate-200">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <h3 class="font-semibold text-slate-950">Recently checked connections</h3>
                    </div>
                    <div v-if="googleBusinessProfiles.recent.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">No court owner has tried to connect Google yet.</div>
                    <div v-else class="divide-y divide-slate-200">
                        <article v-for="connection in googleBusinessProfiles.recent" :key="`${connection.organization}-${connection.venue}-${connection.updated_at}`" class="grid gap-2 px-4 py-4 text-sm md:grid-cols-[1.2fr_1fr_1fr]">
                            <div>
                                <p class="font-semibold text-slate-950">{{ connection.venue }}</p>
                                <p class="text-slate-500">{{ connection.organization }}</p>
                            </div>
                            <div>
                                <p class="font-medium text-slate-800">{{ statusLabels[connection.status] ?? connection.status }}</p>
                                <p class="text-slate-500">{{ connection.profile_title || 'No profile selected' }}</p>
                            </div>
                            <div>
                                <p class="text-slate-500">{{ formatDate(connection.updated_at) }}</p>
                                <p v-if="connection.last_error_code" class="mt-1 text-amber-800">{{ connection.last_error_code }}: {{ connection.last_error_message }}</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </div>
    </PlatformLayout>
</template>
