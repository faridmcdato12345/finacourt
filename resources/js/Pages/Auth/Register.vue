<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import SocialLoginButtons from '../../Components/SocialLoginButtons.vue';

defineProps({
    socialProviders: { type: Array, default: () => [] },
    claimInvitation: { type: Boolean, default: false },
});

const form = useForm({ name: '', email: '', organization_name: '', password: '', password_confirmation: '' });
const page = usePage();

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head title="Create owner account" />
    <GuestLayout>
        <div class="w-full py-6">
            <p class="text-sm font-semibold text-court-700">For court owners</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Create your organization</h1>
            <p class="mt-3 text-slate-600">This account becomes the organization owner. Staff access can be added later.</p>

            <section v-if="claimInvitation" class="mt-6 rounded-2xl border border-court-200 bg-court-50 p-5 text-court-950">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Private venue invitation</p>
                <p class="mt-2 text-sm leading-6 text-court-900/80">Create your court-owner account and verify its email. FinACourt will then return you to the private venue invitation to continue the ownership request.</p>
                <p class="mt-3 text-sm">Already registered? <Link href="/login" class="font-semibold text-court-700 hover:text-court-800">Sign in instead</Link></p>
            </section>

            <p v-if="page.props.errors?.social" role="alert" class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ page.props.errors.social }}</p>
            <SocialLoginButtons :providers="socialProviders" />

            <form :class="socialProviders.length ? '' : 'mt-8'" class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-800">Your name</label>
                    <input id="name" v-model="form.name" required autocomplete="name" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="organization_name" class="mb-1.5 block text-sm font-medium text-slate-800">Organization name</label>
                    <input id="organization_name" v-model="form.organization_name" required autocomplete="organization" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.organization_name" class="mt-1.5 text-sm text-red-600">{{ form.errors.organization_name }}</p>
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-slate-800">Email address</label>
                    <input id="email" v-model="form.email" type="email" required autocomplete="email" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-slate-800">Password</label>
                        <input id="password" v-model="form.password" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-court-600" />
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-800">Confirm password</label>
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm focus:border-court-600" />
                    </div>
                </div>
                <button type="submit" :disabled="form.processing" class="mt-2 w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Creating workspace…' : 'Create owner workspace' }}</button>
            </form>
            <p class="mt-4 text-center text-xs leading-5 text-slate-500">By creating an account, you agree to FinACourt's <a href="/terms" class="font-semibold text-court-700 hover:underline">Terms of Service</a> and acknowledge the <a href="/privacy" class="font-semibold text-court-700 hover:underline">Privacy Policy</a>.</p>
            <p class="mt-6 text-center text-sm text-slate-600">Already have an account? <Link href="/login" class="font-semibold text-court-700">Sign in</Link></p>
        </div>
    </GuestLayout>
</template>
