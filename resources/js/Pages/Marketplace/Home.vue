<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head>
        <title>Find a court. Get in the game.</title>
        <meta name="description" content="FinACourt connects players with local sports facilities and gives court owners a simpler operating home." />
    </Head>
    <PublicLayout>
        <section class="relative overflow-hidden bg-white">
            <div class="absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-court-50 to-transparent"></div>
            <div class="relative mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:py-32">
                <div class="max-w-3xl">
                    <p class="mb-5 text-sm font-semibold uppercase tracking-[0.18em] text-court-700">Play local</p>
                    <h1 class="text-5xl font-semibold leading-[1.04] tracking-[-0.045em] text-slate-950 sm:text-6xl lg:text-7xl">More time on court. Less time searching.</h1>
                    <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">A marketplace for finding nearby courts, and an operating home that helps local facilities serve players better.</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <Link href="/register" class="rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-court-800">List your courts</Link>
                        <Link href="/login" class="rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Owner sign in</Link>
                    </div>
                </div>

                <div class="relative mx-auto w-full max-w-lg">
                    <div class="absolute -inset-5 rotate-3 rounded-[2rem] bg-court-100"></div>
                    <div class="relative overflow-hidden rounded-[1.75rem] bg-court-950 p-7 text-white shadow-2xl sm:p-9">
                        <div class="grid aspect-[4/3] grid-cols-2 grid-rows-2 gap-px overflow-hidden rounded-2xl border border-white/15 bg-white/15">
                            <div class="border-b border-r border-white/15"></div>
                            <div class="border-b border-white/15"></div>
                            <div class="border-r border-white/15"></div>
                            <div></div>
                            <div class="absolute left-1/2 top-9 h-[calc(100%-4.5rem)] w-px bg-white/40"></div>
                            <div class="absolute left-9 top-1/2 h-px w-[calc(100%-4.5rem)] bg-white/40"></div>
                            <span class="absolute left-1/2 top-1/2 size-16 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/40"></span>
                        </div>
                        <div class="mt-6 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-court-300">Foundation ready</p>
                                <p class="mt-2 text-xl font-semibold">Secure owner workspaces</p>
                            </div>
                            <span class="grid size-11 place-items-center rounded-full bg-court-400 text-court-950">→</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-stone-50">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-16 sm:px-8 md:grid-cols-3">
                <div v-for="item in [
                    ['Discover', 'Browse real local facilities without an app install.'],
                    ['Operate', 'Give every owner and authorized staff member the right workspace.'],
                    ['Grow', 'Build toward bookings, promotions, and measurable demand.'],
                ]" :key="item[0]" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="font-semibold text-court-700">{{ item[0] }}</p>
                    <p class="mt-2 leading-7 text-slate-600">{{ item[1] }}</p>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
