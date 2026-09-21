<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import LogoutForm from '../Components/LogoutForm.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

const page = usePage();
const mobileNavigationOpen = ref(false);

const navigationItems = [
    { href: '/platform/dashboard', label: 'Overview' },
    { href: '/platform/analytics', label: 'Analytics' },
    { href: '/platform/payments', label: 'Payments' },
    { href: '/platform/court-closures', label: 'Closure refunds' },
    { href: '/platform/owner-payouts', label: 'Owner payouts' },
    { href: '/platform/growth', label: 'Growth rules' },
    { href: '/platform/reviews', label: 'Reviews' },
    { href: '/platform/venue-applications', label: 'Venue applications' },
    { href: '/platform/directory', label: 'Venue guide' },
    { href: '/platform/sales', label: 'Sales partners' },
];

function isActive(href) {
    return page.url.startsWith(href);
}

function closeMobileNavigation() {
    mobileNavigationOpen.value = false;
}

watch(() => page.url, closeMobileNavigation);
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-950 text-white" @keydown.esc="closeMobileNavigation">
            <div class="mx-auto flex min-h-18 max-w-[100rem] items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <div>
                    <a href="/" class="flex items-center gap-2.5 font-semibold tracking-tight"><img :src="'/icons/app-logo.png'" alt="" class="size-9 rounded-xl object-contain" width="36" height="36">FinACourt</a>
                    <p class="mt-0.5 text-xs uppercase tracking-wider text-court-300">Platform administration</p>
                </div>
                <nav class="hidden items-center gap-4 text-sm 2xl:flex" aria-label="Platform navigation">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        :class="isActive(item.href) ? 'font-semibold text-white' : 'text-slate-300 hover:text-white'"
                    >{{ item.label }}</Link>
                    <span class="hidden text-slate-300 sm:inline">{{ page.props.auth.user.name }}</span>
                    <ThemeToggle />
                    <LogoutForm class="rounded-lg border border-slate-700 px-3 py-2 hover:bg-slate-900 disabled:cursor-wait disabled:opacity-60" />
                </nav>
                <div class="flex items-center gap-2 2xl:hidden">
                    <span class="mr-1 hidden max-w-48 truncate text-sm text-slate-300 md:inline">{{ page.props.auth.user.name }}</span>
                    <ThemeToggle />
                    <button
                        type="button"
                        class="grid size-10 place-items-center rounded-xl border border-slate-700 text-court-100 transition hover:border-court-500 hover:bg-slate-900 hover:text-white"
                        aria-controls="platform-navigation-menu"
                        :aria-expanded="mobileNavigationOpen"
                        :aria-label="mobileNavigationOpen ? 'Close platform navigation' : 'Open platform navigation'"
                        @click="mobileNavigationOpen = !mobileNavigationOpen"
                    >
                        <svg v-if="!mobileNavigationOpen" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                        <svg v-else viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>
            <div v-show="mobileNavigationOpen" id="platform-navigation-menu" class="border-t border-slate-800 px-5 pb-5 pt-4 sm:px-8 2xl:hidden">
                <nav class="mx-auto grid max-h-[calc(100dvh-8rem)] max-w-7xl gap-1 overflow-y-auto text-sm sm:grid-cols-2 lg:grid-cols-3" aria-label="Responsive platform navigation" @click="closeMobileNavigation">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.href"
                        :href="item.href"
                        :class="['rounded-xl px-4 py-3 font-medium transition', isActive(item.href) ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-200 hover:bg-white/10 hover:text-white']"
                    >{{ item.label }}</Link>
                    <LogoutForm class="rounded-xl px-4 py-3 text-left font-medium text-slate-200 hover:bg-white/10 hover:text-white disabled:cursor-wait disabled:opacity-60" />
                </nav>
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto max-w-7xl px-5 py-10 sm:px-8">
            <div v-if="page.props.flash?.status" role="status" class="mb-6 rounded-xl border border-court-200 bg-court-50 px-4 py-3 text-sm font-medium text-court-900">{{ page.props.flash.status }}</div>
            <slot />
        </main>
    </div>
</template>
