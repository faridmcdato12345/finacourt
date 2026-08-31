<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ profile: { type: Object, required: true } });
const page = usePage();
const consentOpen = ref(false);
const busy = ref(false);
const copied = ref(false);
const error = computed(() => page.props.errors?.google || null);

function connect() {
    busy.value = true;
    router.post(props.profile.routes.connect, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function retryDiscovery() {
    busy.value = true;
    router.post(props.profile.routes.retry, {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function refreshStatus() {
    router.reload({ only: ['googleBusinessProfile'], preserveScroll: true });
}

function confirmCandidate(key) {
    busy.value = true;
    router.post(props.profile.routes.confirm_base.replace(/0{64}$/, key), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function disconnect() {
    if (!window.confirm('Disconnect Google from this venue? This will not delete or change the Google profile.')) return;
    busy.value = true;
    router.delete(props.profile.routes.disconnect, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

async function copyUrl() {
    if (!props.profile.public_page_ready || !navigator.clipboard) return;
    await navigator.clipboard.writeText(props.profile.public_url);
    copied.value = true;
    window.setTimeout(() => { copied.value = false; }, 1800);
}
</script>

<template>
    <section id="google-business-profile" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="grid gap-6 border-b border-slate-200 bg-[linear-gradient(120deg,#f0fbf5_0%,#ffffff_66%)] p-6 lg:grid-cols-[1fr_auto] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-700">Google visibility</p>
                <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">Help players find this venue on Google</h3>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Check whether your FinACourt details are ready, then connect a Google account that already manages this venue. Connecting is optional.</p>
            </div>
            <div class="flex items-center gap-4 rounded-2xl border border-court-100 bg-white px-5 py-4 shadow-sm">
                <div class="grid size-16 place-items-center rounded-full border-[7px] border-court-100 text-center">
                    <strong class="text-lg text-court-900">{{ profile.readiness.score }}%</strong>
                </div>
                <div><p class="text-sm font-semibold text-slate-950">Ready for Google</p><p class="mt-1 text-xs text-slate-500">A checklist, not a ranking promise</p></div>
            </div>
        </div>

        <div class="grid gap-7 p-6 xl:grid-cols-[1.05fr_.95fr]">
            <div>
                <h4 class="text-lg font-semibold text-slate-950">Before you connect</h4>
                <p class="mt-1 text-sm leading-6 text-slate-500">These details help FinACourt compare your venue with profiles your Google account can manage.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div v-for="check in profile.readiness.checks" :key="check.code" :class="['rounded-xl border p-3.5', check.complete ? 'border-court-100 bg-court-50/60' : 'border-slate-200 bg-slate-50']">
                        <div class="flex items-start gap-3">
                            <span :class="['grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold', check.complete ? 'bg-court-700 text-white' : 'bg-white text-slate-500 ring-1 ring-slate-200']">{{ check.complete ? '✓' : '!' }}</span>
                            <div><p class="text-sm font-semibold text-slate-900">{{ check.label }}</p><p class="mt-1 text-xs leading-5 text-slate-500">{{ check.complete ? 'Ready' : check.guidance }}</p></div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="min-w-0 flex-1">
                            <span class="text-xs font-semibold text-slate-700">FinACourt venue page</span>
                            <input :value="profile.public_url" readonly class="mt-1.5 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs text-slate-600">
                        </label>
                        <button type="button" :disabled="!profile.public_page_ready" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-court-800 disabled:cursor-not-allowed disabled:text-slate-400" @click="copyUrl">{{ copied ? 'Copied' : 'Copy link' }}</button>
                    </div>
                    <p :class="['mt-2 text-xs', profile.public_page_ready ? 'text-court-700' : 'text-amber-700']">{{ profile.public_page_ready ? 'This is the live booking page FinACourt can use for Google.' : 'Not yet published. Finish the missing items above before sharing this page.' }}</p>
                </div>
            </div>

            <div class="space-y-4">
                <div :class="['rounded-2xl border p-5', profile.status === 'connected' ? 'border-court-200 bg-court-50/50' : profile.status === 'pending_discovery' ? 'border-sky-200 bg-sky-50/40' : 'border-slate-200 bg-white']">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Google connection</p><h4 class="mt-1 text-lg font-semibold text-slate-950">{{ profile.status_label }}</h4></div>
                        <span :class="['mt-1 size-3 rounded-full', profile.status === 'connected' ? 'bg-emerald-500' : profile.status === 'pending_discovery' ? 'animate-pulse bg-sky-500' : profile.status === 'needs_confirmation' ? 'bg-amber-400' : 'bg-slate-300']"></span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ profile.status_detail }}</p>
                    <p v-if="error" role="alert" class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-700">{{ error }}</p>

                    <div v-if="profile.connected_profile" class="mt-4 rounded-xl border border-court-100 bg-white p-4 text-sm">
                        <strong class="text-slate-950">{{ profile.connected_profile.title }}</strong>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ profile.connected_profile.address }}</p>
                        <p class="mt-2 text-xs text-slate-400">Managed through {{ profile.connected_profile.account_label }}</p>
                    </div>

                    <div v-if="!profile.available" class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-xs leading-5 text-slate-600">Platform setup is still needed. The owner can continue editing and publishing the venue without Google.</div>
                    <div v-else class="mt-5 flex flex-wrap gap-2">
                        <button v-if="profile.status === 'pending_discovery'" type="button" disabled class="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white opacity-80">Checking in background…</button>
                        <button v-else-if="profile.can_retry" type="button" :disabled="busy" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:opacity-60" @click="retryDiscovery">Try again</button>
                        <button v-else type="button" :disabled="busy" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:opacity-60" @click="consentOpen = true">{{ profile.status === 'connected' ? 'Reconnect Google' : 'Connect Google' }}</button>
                        <button v-if="profile.status === 'pending_discovery'" type="button" class="rounded-xl border border-sky-200 bg-white px-4 py-2.5 text-sm font-semibold text-sky-800 hover:bg-sky-50" @click="refreshStatus">Check status</button>
                        <button v-if="profile.connected_profile" type="button" :disabled="busy" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60" @click="disconnect">Disconnect</button>
                    </div>
                </div>

                <div v-if="profile.candidates.length" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
                    <h4 class="font-semibold text-slate-950">Which Google profile is this venue?</h4>
                    <p class="mt-1 text-xs leading-5 text-slate-600">Only choose a profile when the name and address describe this exact venue.</p>
                    <div class="mt-4 space-y-3">
                        <article v-for="candidate in profile.candidates" :key="candidate.key" class="rounded-xl border border-amber-100 bg-white p-4">
                            <h5 class="font-semibold text-slate-950">{{ candidate.title || 'Unnamed Google profile' }}</h5>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ candidate.address || 'No address returned by Google' }}</p>
                            <p v-if="candidate.phone || candidate.category" class="mt-2 text-xs text-slate-500">{{ [candidate.category, candidate.phone].filter(Boolean).join(' · ') }}</p>
                            <p v-if="candidate.signals.length" class="mt-2 text-xs font-medium text-court-700">Matched by: {{ candidate.signals.join(', ') }}</p>
                            <button type="button" :disabled="busy" class="mt-3 rounded-lg border border-court-200 px-3 py-2 text-xs font-semibold text-court-800 hover:bg-court-50 disabled:opacity-60" @click="confirmCandidate(candidate.key)">This is my venue</button>
                        </article>
                    </div>
                </div>

                <details class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm">
                    <summary class="cursor-pointer font-semibold text-slate-900">What FinACourt will and will not do</summary>
                    <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-600">
                        <li>• Google will ask you to choose an account and approve Business Profile access.</li>
                        <li>• FinACourt reads only profiles that account already manages so you can select the correct venue.</li>
                        <li>• This version does not create, verify, edit, or publish a Google profile.</li>
                        <li>• Disconnecting removes FinACourt’s saved access; it does not delete the Google profile.</li>
                    </ul>
                </details>
            </div>
        </div>

        <div v-if="consentOpen" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/55 p-4" role="dialog" aria-modal="true" aria-labelledby="google-consent-title" @click.self="consentOpen = false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 id="google-consent-title" class="text-xl font-semibold text-slate-950">Connect this venue to Google?</h3>
                <p class="mt-3 text-sm leading-6 text-slate-600">Google will show its own sign-in and permission screen. Use an account that already owns or manages this venue’s Google Business Profile.</p>
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-xs leading-5 text-slate-600">FinACourt will read the account’s business names, addresses, phone numbers, hours, map positions, and profile identifiers to find a safe match. It will not edit Google in this version.</div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700" @click="consentOpen = false">Not now</button>
                    <button type="button" :disabled="busy" class="rounded-xl bg-court-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-60" @click="connect">Continue to Google</button>
                </div>
            </div>
        </div>
    </section>
</template>
