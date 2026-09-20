<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps({
    invitation: Object,
    requiresLogin: Boolean,
    requiresRegistration: Boolean,
    accountMismatch: Boolean,
    canAccept: Boolean,
    acceptUrl: String,
    loginUrl: String,
    logoutUrl: String,
});

const page = usePage();
const registration = useForm({ name: '', password: '', password_confirmation: '' });
const acceptance = useForm({});

function registerAndAccept() {
    registration.post(props.acceptUrl, {
        onFinish: () => registration.reset('password', 'password_confirmation'),
    });
}

function accept() {
    acceptance.post(props.acceptUrl);
}

function logout() {
    router.post(props.logoutUrl);
}
</script>

<template>
    <Head title="Staff invitation" />
    <GuestLayout>
        <div class="w-full py-6">
            <p class="text-sm font-semibold text-court-700">Team invitation</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Join {{ invitation.organization }}</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">You were invited as a FinACourt staff member using <strong>{{ invitation.email }}</strong>.</p>

            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-950">Access included</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li v-for="permission in invitation.permissions" :key="permission" class="flex gap-2"><span class="text-court-600">✓</span><span>{{ permission }}</span></li>
                </ul>
                <p class="mt-4 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500">Payouts, payment-account details, and team administration remain available only to the court owner.</p>
            </section>

            <div v-if="invitation.status === 'expired'" role="alert" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
                This invitation expired on {{ invitation.expires_at }}. Ask the court owner to resend it.
            </div>
            <div v-else-if="invitation.status === 'revoked'" role="alert" class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-900">
                This invitation was revoked by the court owner and can no longer be used.
            </div>
            <div v-else-if="invitation.status === 'accepted'" class="mt-6 rounded-2xl border border-court-200 bg-court-50 p-5 text-sm text-court-950">
                This invitation has already been accepted. <Link href="/login" class="font-semibold underline">Sign in to FinACourt</Link>.
            </div>
            <div v-else-if="accountMismatch" role="alert" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                You are signed in with a different account. Sign out, then open this invitation again using {{ invitation.email }}.
                <button type="button" class="mt-3 block font-semibold underline" @click="logout">Sign out</button>
            </div>
            <div v-else-if="requiresLogin" class="mt-6 rounded-2xl border border-court-200 bg-court-50 p-5 text-sm leading-6 text-court-950">
                A FinACourt account already uses this email. Sign in to that account; we will return you to this invitation.
                <Link :href="loginUrl" class="mt-4 inline-flex rounded-xl bg-court-700 px-4 py-2.5 font-semibold text-white">Sign in to accept</Link>
            </div>

            <form v-else-if="requiresRegistration" class="mt-6 space-y-4" @submit.prevent="registerAndAccept">
                <p class="rounded-xl bg-court-50 px-4 py-3 text-sm text-court-900">Create your staff account. Opening this private email link verifies the invited email address.</p>
                <label class="block"><span class="text-sm font-medium text-slate-800">Your name</span><input v-model="registration.name" required autocomplete="name" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="registration.errors.name" class="mt-1 block text-sm text-red-600">{{ registration.errors.name }}</span></label>
                <label class="block"><span class="text-sm font-medium text-slate-800">Password</span><input v-model="registration.password" required type="password" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"><span v-if="registration.errors.password" class="mt-1 block text-sm text-red-600">{{ registration.errors.password }}</span></label>
                <label class="block"><span class="text-sm font-medium text-slate-800">Confirm password</span><input v-model="registration.password_confirmation" required type="password" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                <p v-if="registration.errors.invitation || page.props.errors?.invitation" role="alert" class="text-sm text-red-600">{{ registration.errors.invitation || page.props.errors?.invitation }}</p>
                <button type="submit" :disabled="registration.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-60">{{ registration.processing ? 'Joining…' : 'Create account and join team' }}</button>
            </form>

            <div v-else-if="canAccept" class="mt-6">
                <p v-if="acceptance.errors.invitation" role="alert" class="mb-3 text-sm text-red-600">{{ acceptance.errors.invitation }}</p>
                <button type="button" :disabled="acceptance.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-60" @click="accept">{{ acceptance.processing ? 'Joining…' : `Join ${invitation.organization}` }}</button>
            </div>

            <p v-if="invitation.status === 'pending'" class="mt-5 text-center text-xs text-slate-500">Invitation expires {{ invitation.expires_at }}.</p>
        </div>
    </GuestLayout>
</template>
