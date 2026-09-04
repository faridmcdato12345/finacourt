<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps({
    email: { type: String, required: true },
    accountSettingsUrl: { type: String, required: true },
    isOwnerVerification: { type: Boolean, default: false },
    routes: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const resendForm = useForm({});
const logoutForm = useForm({});
const verificationSent = computed(() => page.props.flash?.status === 'verification-link-sent');

function resendVerification() {
    resendForm.post(props.routes.resend, { preserveScroll: true });
}

function signOut() {
    logoutForm.post(props.routes.logout);
}
</script>

<template>
    <Head title="Verify your account email" />
    <GuestLayout>
        <section class="w-full rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-court-700">
                {{ isOwnerVerification ? 'Protect venue ownership' : 'Protect your account' }}
            </p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">Verify your account email</h1>
            <p class="mt-4 text-sm leading-7 text-slate-600">
                We sent a verification link to <strong class="font-semibold text-slate-800">{{ email }}</strong>.
                <template v-if="isOwnerVerification">
                    Confirm this address before using the court-owner workspace. Email verification does not prove venue ownership by itself; venue and marketplace checks still happen separately.
                </template>
                <template v-else>
                    Confirm this address to finish securing your FinACourt account.
                </template>
            </p>

            <div v-if="verificationSent" role="status" class="mt-6 rounded-xl border border-court-200 bg-court-50 p-4 text-sm text-court-900">
                A fresh verification email is on its way. It may take a few minutes to arrive.
            </div>

            <form class="mt-7" @submit.prevent="resendVerification">
                <button type="submit" :disabled="resendForm.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">
                    {{ resendForm.processing ? 'Sending…' : 'Send verification email' }}
                </button>
            </form>

            <div class="mt-4 flex items-center justify-between gap-4 text-sm">
                <Link :href="accountSettingsUrl" class="font-semibold text-court-700">Change email or password</Link>
                <button type="button" :disabled="logoutForm.processing" class="text-slate-500 hover:text-slate-800 disabled:cursor-wait disabled:opacity-60" @click="signOut">Sign out</button>
            </div>
        </section>
    </GuestLayout>
</template>
