<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import LogoutForm from '../Components/LogoutForm.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="border-b border-slate-800 bg-slate-950 text-white">
            <div class="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <div>
                    <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight"><img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-9 rounded-xl object-cover" width="36" height="36">FinACourt</a>
                    <p class="mt-0.5 text-xs uppercase tracking-wider text-court-300">Platform administration</p>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/platform/dashboard" class="text-slate-300 hover:text-white">Overview</Link>
                    <Link href="/platform/analytics" class="text-slate-300 hover:text-white">Analytics</Link>
                    <Link href="/platform/payments" class="text-slate-300 hover:text-white">Payments</Link>
                    <Link href="/platform/owner-payouts" class="text-slate-300 hover:text-white">Owner payouts</Link>
                    <Link href="/platform/growth" class="text-slate-300 hover:text-white">Growth rules</Link>
                    <Link href="/platform/reviews" class="text-slate-300 hover:text-white">Reviews</Link>
                    <Link href="/platform/directory" class="text-slate-300 hover:text-white">Venue guide</Link>
                    <Link href="/platform/sales" class="text-slate-300 hover:text-white">Sales partners</Link>
                    <span class="hidden text-slate-300 sm:inline">{{ page.props.auth.user.name }}</span>
                    <ThemeToggle />
                    <LogoutForm class="rounded-lg border border-slate-700 px-3 py-2 hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60" />
                </div>
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
            <slot />
        </main>
    </div>
</template>
