<script setup>
import { useForm } from '@inertiajs/vue3';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    account: { type: Object, required: true },
    routes: { type: Object, required: true },
});

const profileForm = useForm({
    name: props.account.name,
    email: props.account.email,
    profile_current_password: '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordLinkForm = useForm({});

function saveProfile() {
    profileForm.patch(props.routes.profile, {
        preserveScroll: true,
        onSuccess: () => profileForm.reset('profile_current_password'),
    });
}

function changePassword() {
    passwordForm.put(props.routes.password, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function sendPasswordLink() {
    passwordLinkForm.post(props.routes.password_link, { preserveScroll: true });
}
</script>

<template>
    <OwnerLayout>
        <div class="mx-auto max-w-4xl">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Your account</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Profile and password</h1>
                <p class="mt-3 leading-7 text-slate-500">Keep your own name, sign-in email, and password up to date. These changes do not alter your venue details.</p>
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2 lg:items-start">
                <form class="app-card p-6 sm:p-7" @submit.prevent="saveProfile">
                    <div class="flex items-start gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-court-50 text-lg font-semibold text-court-800">{{ account.name.slice(0, 1).toUpperCase() }}</span>
                        <div><h2 class="text-xl font-semibold">Your details</h2><p class="mt-1 text-sm leading-6 text-slate-500">Used when FinACourt contacts you about your account.</p></div>
                    </div>

                    <label class="mt-6 block">
                        <span class="text-sm font-semibold text-slate-800">Your name</span>
                        <input v-model="profileForm.name" name="name" type="text" autocomplete="name" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                        <FormError :message="profileForm.errors.name" />
                    </label>

                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-slate-800">Sign-in email</span>
                        <input v-model="profileForm.email" name="email" type="email" autocomplete="email" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                        <FormError :message="profileForm.errors.email" />
                    </label>

                    <div v-if="!account.email_verified" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">This email address still needs verification. Check your inbox for the FinACourt verification message.</div>

                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-slate-800">Current password <span class="font-normal text-slate-400">(only needed to change your email)</span></span>
                        <input v-model="profileForm.profile_current_password" name="profile_current_password" type="password" autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                        <FormError :message="profileForm.errors.profile_current_password" />
                    </label>

                    <p class="mt-4 text-xs leading-5 text-slate-500">If you change your email, FinACourt sends a verification link to the new address.</p>
                    <button type="submit" :disabled="profileForm.processing" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">{{ profileForm.processing ? 'Saving…' : 'Save my details' }}</button>
                </form>

                <form class="app-card p-6 sm:p-7" @submit.prevent="changePassword">
                    <div class="flex items-start gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-slate-100 text-slate-700">
                            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2" /></svg>
                        </span>
                        <div><h2 class="text-xl font-semibold">Change password</h2><p class="mt-1 text-sm leading-6 text-slate-500">Use a password you do not use on another website.</p></div>
                    </div>

                    <label class="mt-6 block">
                        <span class="text-sm font-semibold text-slate-800">Current password</span>
                        <input v-model="passwordForm.current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                        <FormError :message="passwordForm.errors.current_password" />
                    </label>

                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-slate-800">New password</span>
                        <input v-model="passwordForm.password" name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                        <FormError :message="passwordForm.errors.password" />
                    </label>

                    <label class="mt-5 block">
                        <span class="text-sm font-semibold text-slate-800">Repeat new password</span>
                        <input v-model="passwordForm.password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    </label>

                    <div v-if="account.connected_sign_ins.length" class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">Connected sign-in: <strong>{{ account.connected_sign_ins.join(', ') }}</strong>. Changing your password does not disconnect these sign-in methods.</div>
                    <button type="submit" :disabled="passwordForm.processing" class="mt-6 min-h-12 w-full rounded-xl bg-court-950 px-5 py-3 text-sm font-semibold text-white hover:bg-court-900 disabled:cursor-wait disabled:opacity-60">{{ passwordForm.processing ? 'Changing…' : 'Change my password' }}</button>
                    <div class="mt-5 border-t border-slate-100 pt-5 text-center">
                        <p class="text-xs leading-5 text-slate-500">Signed up with Google, Facebook, or Apple—or cannot remember your current password?</p>
                        <button type="button" :disabled="passwordLinkForm.processing" class="mt-2 text-sm font-semibold text-court-700 hover:text-court-900 disabled:cursor-wait disabled:opacity-60" @click="sendPasswordLink">{{ passwordLinkForm.processing ? 'Sending…' : 'Email me a secure password link' }}</button>
                        <FormError :message="passwordLinkForm.errors.password_link" />
                    </div>
                </form>
            </div>
        </div>
    </OwnerLayout>
</template>
