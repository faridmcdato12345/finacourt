<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import LogoutForm from '../Components/LogoutForm.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-[#f5f8f6]">
        <header class="border-b border-court-900 bg-court-950 text-white">
            <div class="mx-auto flex min-h-18 max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <div>
                    <a href="/partner/dashboard" class="flex items-center gap-2.5 text-lg font-bold tracking-[0.12em]"><img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-9 rounded-xl object-cover" width="36" height="36">FinACourt</a>
                    <p class="mt-0.5 text-xs uppercase tracking-wider text-court-200">Sales partner workspace</p>
                </div>
                <nav class="flex items-center gap-2 text-sm sm:gap-4" aria-label="Sales partner navigation">
                    <Link href="/partner/dashboard" class="rounded-lg px-2 py-2 text-court-100 hover:bg-white/10">Overview</Link>
                    <Link href="/partner/leads" class="rounded-lg px-2 py-2 text-court-100 hover:bg-white/10">Leads</Link>
                    <span class="hidden text-court-200 md:inline">{{ page.props.auth.user.name }}</span>
                    <ThemeToggle />
                    <LogoutForm class="rounded-lg border border-white/20 px-3 py-2 font-semibold disabled:cursor-wait disabled:opacity-60" />
                </nav>
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
            <div v-if="page.props.flash?.status" role="status" class="mb-6 rounded-xl border border-court-200 bg-court-50 px-4 py-3 text-sm font-medium text-court-900">{{ page.props.flash.status }}</div>
            <slot />
        </main>
    </div>
</template>
