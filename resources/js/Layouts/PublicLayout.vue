<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div class="min-h-screen bg-stone-50">
        <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 sm:px-8">
                <a href="/" class="flex items-center gap-3 font-semibold tracking-tight text-slate-950">
                    <img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-10 rounded-xl object-cover shadow-sm" width="40" height="40">
                    <span>FinACourt</span>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium" aria-label="Primary navigation">
                    <Link v-if="page.props.auth.user && !page.props.auth.user.is_platform_admin" href="/owner/dashboard" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100">Dashboard</Link>
                    <Link v-else-if="page.props.auth.user?.is_platform_admin" href="/platform/dashboard" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100">Platform</Link>
                    <template v-else>
                        <Link href="/login" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100">Sign in</Link>
                        <Link href="/register" class="rounded-lg bg-court-700 px-4 py-2.5 text-white shadow-sm hover:bg-court-800">List your courts</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <p>Built for local court owners and the players they serve.</p>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <a href="/privacy" class="hover:text-court-700">Privacy Policy</a>
                    <a href="/terms" class="hover:text-court-700">Terms of Service</a>
                    <p>© {{ new Date().getFullYear() }} FinACourt</p>
                </div>
            </div>
        </footer>
    </div>
</template>
