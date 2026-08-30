<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import SocialLoginButtons from '../../Components/SocialLoginButtons.vue';

defineProps({ socialProviders: { type: Array, default: () => [] } });

const form = useForm({ email: '', password: '', remember: false });
const page = usePage();

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Sign in" />
    <GuestLayout>
        <div class="w-full">
            <p class="text-sm font-semibold text-court-700">Welcome back</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Sign in to your workspace</h1>
            <p class="mt-3 text-slate-600">Use the account associated with your court organization.</p>

            <p v-if="page.props.errors?.social" role="alert" class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ page.props.errors.social }}</p>
            <SocialLoginButtons :providers="socialProviders" />

            <form :class="socialProviders.length ? '' : 'mt-8'" class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-800">Email address</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required autofocus class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-950 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-800">Password</label>
                    <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-950 shadow-sm focus:border-court-600" />
                    <p v-if="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="size-4 rounded border-slate-300 text-court-700" />
                    Keep me signed in
                </label>
                <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800 disabled:cursor-wait disabled:opacity-60">{{ form.processing ? 'Signing in…' : 'Sign in' }}</button>
            </form>

            <p class="mt-7 text-center text-sm text-slate-600">New court owner? <Link href="/register" class="font-semibold text-court-700 hover:text-court-800">Create an account</Link></p>
        </div>
    </GuestLayout>
</template>
