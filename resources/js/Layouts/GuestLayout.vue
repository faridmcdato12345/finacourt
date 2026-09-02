<script setup>
import ThemeToggle from '../Components/ThemeToggle.vue';
</script>

<template>
    <div class="grid min-h-screen bg-stone-50 lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.8fr)]">
        <section class="flex min-h-screen flex-col px-5 py-6 sm:px-10 lg:px-14">
            <div class="flex items-center justify-between gap-4">
                <a href="/" class="flex w-fit items-center gap-3 font-semibold tracking-tight text-slate-950">
                    <img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-10 rounded-xl object-cover" width="40" height="40">
                    <span class="tracking-[0.1em] text-court-900">FinACourt</span>
                </a>
                <ThemeToggle />
            </div>

            <main id="main-content" tabindex="-1" class="mx-auto flex w-full max-w-md flex-1 items-center py-12">
                <slot />
            </main>

            <footer class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 border-t border-slate-200 pt-5 text-xs text-slate-500 sm:justify-start">
                <a href="/privacy" class="hover:text-court-700">Privacy Policy</a>
                <a href="/terms" class="hover:text-court-700">Terms of Service</a>
                <span>© {{ new Date().getFullYear() }} FinACourt</span>
            </footer>
        </section>

        <aside class="relative hidden overflow-hidden bg-court-950 p-12 text-white lg:flex lg:flex-col lg:justify-end">
            <div class="absolute -right-28 -top-28 size-96 rounded-full border border-white/10"></div>
            <div class="absolute -right-8 -top-8 size-72 rounded-full border border-white/10"></div>
            <div class="relative max-w-lg">
                <p class="mb-5 text-sm font-semibold uppercase tracking-[0.18em] text-court-300">For court owners</p>
                <p class="text-4xl font-semibold leading-tight tracking-tight">A clear home base for your courts, your team, and your bookings.</p>
                <p class="mt-5 text-base leading-7 text-court-100/75">Manage courts, schedules, bookings, deals, payments, and player visits in one secure place.</p>
            </div>
        </aside>
    </div>
</template>
