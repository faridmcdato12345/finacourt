<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '../Components/AppSelect.vue';
import LogoutForm from '../Components/LogoutForm.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const organization = computed(() => page.props.currentOrganization);
const organizations = computed(() => page.props.organizations || []);
const abilities = computed(() => page.props.abilities || {});

function isActive(prefix) {
    return page.url.startsWith(prefix);
}

function switchOrganization(id) {
    if (String(id) !== String(organization.value.id)) {
        router.post(`/owner/organizations/${id}/activate`);
    }
}
</script>

<template>
    <div class="min-h-screen bg-[#f6f8f5]">
        <header class="sticky top-0 z-40 border-b border-court-900 bg-court-950 text-white lg:hidden">
            <div class="flex h-16 items-center justify-between gap-4 px-4">
                <a href="/owner/dashboard" class="flex items-center gap-2.5 text-lg font-bold tracking-[0.08em]">
                    <img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-9 rounded-xl object-cover" width="36" height="36">
                    FinACourt
                </a>
                <div class="flex items-center gap-2">
                    <ThemeToggle />
                    <a href="/" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-semibold text-court-100">Public site</a>
                </div>
            </div>
            <nav class="owner-mobile-navigation scrollbar-none flex gap-1 overflow-x-auto px-3 pb-3 text-sm" aria-label="Mobile owner navigation">
                <Link href="/owner/dashboard" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/dashboard') ? 'bg-white text-court-950' : 'text-court-100']">Home</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/venues" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/venues') ? 'bg-white text-court-950' : 'text-court-100']">Venues</Link>
                <Link v-if="abilities.manage_bookings" href="/owner/bookings" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/bookings') ? 'bg-white text-court-950' : 'text-court-100']">Bookings</Link>
                <Link v-if="organization?.role === 'owner'" href="/owner/earnings" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/earnings') ? 'bg-white text-court-950' : 'text-court-100']">Earnings</Link>
                <Link v-if="abilities.manage_bookings" href="/owner/reactivation" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/reactivation') ? 'bg-white text-court-950' : 'text-court-100']">Customers</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/promotions" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/promotions') ? 'bg-white text-court-950' : 'text-court-100']">Deals</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/visibility" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/visibility') ? 'bg-white text-court-950' : 'text-court-100']">Get found</Link>
                <Link v-if="organization?.role === 'owner'" href="/owner/directory-claims" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/directory-claims') ? 'bg-white text-court-950' : 'text-court-100']">Venue requests</Link>
                <Link href="/owner/growth" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/growth') ? 'bg-white text-court-950' : 'text-court-100']">More bookings</Link>
                <Link href="/owner/analytics" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/analytics') ? 'bg-white text-court-950' : 'text-court-100']">Visits</Link>
                <Link href="/owner/account" :class="['whitespace-nowrap rounded-lg px-3 py-2', isActive('/owner/account') ? 'bg-white text-court-950' : 'text-court-100']">My account</Link>
            </nav>
        </header>

        <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col overflow-y-auto bg-[linear-gradient(180deg,#063c2a_0%,#075438_54%,#073b2b_100%)] px-4 py-6 text-white lg:flex">
            <a href="/" class="flex items-center gap-3 px-3 text-xl font-bold tracking-[0.1em]">
                <img :src="'/icons/finacourt-logo-192.png'" alt="" class="size-10 rounded-xl object-cover" width="40" height="40">
                FinACourt
            </a>

            <div class="mt-7 rounded-2xl border border-white/20 bg-white/8 p-4 shadow-inner">
                <div class="flex items-center gap-3">
                    <div class="court-visual grid size-12 shrink-0 place-items-center rounded-xl border border-white/15"><span class="relative z-10 text-sm font-bold">{{ organization?.name?.slice(0, 1) }}</span></div>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ organization?.name }}</p><p class="mt-1 text-xs capitalize text-court-200">{{ organization?.role || 'Platform administrator' }}</p></div>
                </div>
                <label v-if="organizations.length > 1" class="mt-4 block">
                    <span class="sr-only">Switch organization</span>
                    <AppSelect
                        :model-value="organization.id"
                        :options="organizations"
                        option-value="id"
                        option-label="name"
                        size="sm"
                        aria-label="Switch organization"
                        class="border-white/20 bg-court-950/70 text-xs text-white shadow-none hover:border-court-300 focus-visible:border-court-300 focus-visible:ring-court-300/20 [&_svg]:text-court-200"
                        @change="switchOrganization"
                    />
                </label>
            </div>

            <nav class="owner-sidebar-navigation mt-6 space-y-1.5" aria-label="Owner navigation">
                <Link href="/owner/dashboard" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/dashboard') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-12h6V4h-6v4Z" /></svg>Home</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/venues" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/venues') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20V8l8-4 8 4v12M8 20v-6h8v6M9 10h.01M15 10h.01" /></svg>Venues & courts</Link>
                <Link v-if="abilities.manage_bookings" href="/owner/bookings" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/bookings') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 3v3m12-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Zm3 8h3v3H8v-3Z" /></svg>Bookings</Link>
                <Link v-if="organization?.role === 'owner'" href="/owner/earnings" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/earnings') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7h18v12H3V7Zm3-3h12M7 13h.01M12 11a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm5 2h.01" /></svg>Court earnings</Link>
                <Link v-if="abilities.manage_bookings" href="/owner/reactivation" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/reactivation') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-3v6m-3-3h6" /></svg>Customers</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/promotions" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/promotions') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 12 12 4h7v7l-8 8-7-7Zm11-4h.01M8 13l3 3" /></svg>Deals</Link>
                <Link v-if="abilities.manage_inventory" href="/owner/visibility" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/visibility') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /></svg>Get found</Link>
                <Link v-if="organization?.role === 'owner'" href="/owner/directory-claims" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/directory-claims') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Zm-3 9 2 2 4-5" /></svg>Venue requests</Link>
                <Link href="/owner/growth" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/growth') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m4 17 5-5 4 3 7-8M15 7h5v5" /></svg>More bookings</Link>
                <Link href="/owner/analytics" :class="['flex items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-medium', isActive('/owner/analytics') ? 'bg-white text-court-900 shadow-sm' : 'text-court-50 hover:bg-white/10']"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 20V10m6 10V4m6 16v-7m4 7H2" /></svg>Visits & bookings</Link>
            </nav>

            <div class="mt-auto pt-8">
                <a href="/" class="mb-4 flex items-center justify-between rounded-xl border border-white/15 bg-white/8 px-4 py-3 text-xs font-semibold text-court-50 hover:bg-white/12"><span>View public site</span><span>↗</span></a>
                <div class="flex items-center gap-2 border-t border-white/15 px-2 pt-4">
                    <Link href="/owner/account" class="flex min-w-0 flex-1 items-center gap-3 rounded-xl p-1.5 hover:bg-white/10" aria-label="Open profile and password settings">
                        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-court-200 font-semibold text-court-950">{{ user?.name?.slice(0, 1) }}</span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ user?.name }}</span><span class="block truncate text-xs text-court-200">My account</span></span>
                    </Link>
                    <LogoutForm class="rounded-lg p-2 text-sm font-semibold text-court-200 hover:bg-white/10 hover:text-white disabled:cursor-wait disabled:opacity-60" aria-label="Sign out" />
                </div>
                <Link v-if="user?.is_platform_admin" href="/platform/dashboard" class="mt-3 block rounded-lg px-2 py-2 text-xs font-semibold text-court-200 hover:bg-white/10">Platform admin</Link>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="hidden border-b border-slate-200 bg-white/90 backdrop-blur lg:block"><div class="flex min-h-16 items-center justify-between gap-4 px-6 xl:px-10"><div class="flex items-center gap-2 text-sm text-slate-500"><span class="size-2 rounded-full bg-court-500"></span><span>Your account</span><span class="text-slate-300">/</span><strong class="font-medium text-slate-800">{{ organization?.name }}</strong></div><div class="flex items-center gap-3"><ThemeToggle /><button type="button" data-install-app hidden class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Install app</button><span class="rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold capitalize text-court-800">{{ organization?.role || 'Platform admin' }}</span><Link href="/owner/account" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-court-300 hover:text-court-800">My account</Link></div></div></header>
            <main id="main-content" tabindex="-1" class="px-4 py-6 sm:px-6 sm:py-8 xl:px-10 xl:py-9"><div v-if="page.props.flash?.status" role="status" class="mb-6 flex items-start gap-3 rounded-xl border border-court-200 bg-court-50 px-4 py-3 text-sm font-medium text-court-900"><span aria-hidden="true">✓</span><span>{{ page.props.flash.status }}</span></div><slot /></main>
        </div>
    </div>
</template>
