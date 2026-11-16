<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venues: Array, segments: Array, defaults: Object, campaign: Object });
const existing = props.campaign || {};
const form = useForm({ venue_id: existing.venue_id || props.defaults?.venue_id || props.venues[0]?.id || '', sport_id: existing.sport_id || '', title: existing.title || '', message: existing.message || '', segment: existing.segment || props.defaults?.segment || 'inactive_30', delivery_mode: existing.delivery_mode || 'manual', automation_inactivity_days: existing.automation_inactivity_days || 30, include_exclusive_offer: existing.include_exclusive_offer || false, offer_discount_type: existing.offer_discount_type || 'fixed', offer_discount_value: existing.offer_discount_value || '', offer_minimum_spend: existing.offer_minimum_spend || '', offer_valid_days: existing.offer_valid_days || 7, offer_max_redemptions: existing.offer_max_redemptions || '' });
const selectedVenue = computed(() => props.venues.find((venue) => String(venue.id) === String(form.venue_id)));
const sports = computed(() => [{ id: '', name: 'Any sport offered at this venue' }, ...(selectedVenue.value?.sports || [])]);

function venueChanged() { form.sport_id = ''; }
function submit() { props.campaign ? form.patch(`/owner/reactivation/${props.campaign.id}`) : form.post('/owner/reactivation'); }
</script>

<template>
    <Head title="Message past players" />
    <OwnerLayout>
        <div class="mx-auto max-w-3xl">
            <Link href="/owner/reactivation" class="text-sm font-semibold text-court-700">← Past players</Link>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight">{{ campaign ? 'Edit win-back campaign' : 'Message past players' }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ form.delivery_mode === 'automated' ? 'Saving activates the hourly eligibility check. It does not send immediately.' : 'Saving will not send anything yet. You can review the message first, then send it from the next page.' }}</p>
            <form class="mt-7 space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold">Who should receive it?</h3>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="block"><span class="text-sm font-medium">Venue</span><AppSelect v-model="form.venue_id" :options="venues" option-value="id" option-label="name" class="mt-2 min-h-12" @change="venueChanged" /><FormError :message="form.errors.venue_id" /></label>
                        <label class="block"><span class="text-sm font-medium">Player group</span><AppSelect v-model="form.segment" :options="segments" class="mt-2 min-h-12" /><FormError :message="form.errors.segment" /></label>
                        <label class="block sm:col-span-2"><span class="text-sm font-medium">Sport <span class="font-normal text-slate-400">optional</span></span><AppSelect v-model="form.sport_id" :options="sports" option-value="id" option-label="name" class="mt-2 min-h-12" /><FormError :message="form.errors.sport_id" /></label>
                    </div>
                </section>
                <section class="rounded-2xl border border-court-200 bg-court-50/60 p-5 shadow-sm sm:p-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Delivery</p>
                    <h3 class="mt-1 text-lg font-semibold">Send once or run automatically</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-xl border bg-white p-4" :class="form.delivery_mode === 'manual' ? 'border-court-500 ring-2 ring-court-100' : 'border-slate-200'"><input v-model="form.delivery_mode" type="radio" value="manual" class="sr-only"><strong class="block">Message past players once</strong><span class="mt-1 block text-sm leading-5 text-slate-500">Save a draft, review the eligible audience, then choose when to send it.</span></label>
                        <label class="cursor-pointer rounded-xl border bg-white p-4" :class="form.delivery_mode === 'automated' ? 'border-court-500 ring-2 ring-court-100' : 'border-slate-200'"><input v-model="form.delivery_mode" type="radio" value="automated" class="sr-only"><strong class="block">Automated win-back</strong><span class="mt-1 block text-sm leading-5 text-slate-500">FinACourt checks each hour and contacts a player once per inactivity cycle.</span></label>
                    </div>
                    <label v-if="form.delivery_mode === 'automated'" class="mt-5 block max-w-sm"><span class="text-sm font-medium">Contact after no venue booking for</span><AppSelect v-model="form.automation_inactivity_days" :options="[{ value: 14, label: '14 days' }, { value: 30, label: '30 days' }, { value: 60, label: '60 days' }]" class="mt-2 min-h-12" /><p class="mt-1 text-xs leading-5 text-slate-500">Players with a current or future booking at this venue are excluded automatically.</p><FormError :message="form.errors.automation_inactivity_days" /></label>
                </section>
                <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm sm:p-6">
                    <div class="flex items-start justify-between gap-5">
                        <div><p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Optional incentive</p><h3 class="mt-1 text-lg font-semibold">Include an exclusive welcome-back discount</h3><p class="mt-1 text-sm leading-6 text-slate-600">Each eligible player receives an account-bound, single-use offer. It never stacks with a public deal; FinACourt automatically uses whichever eligible discount saves them more.</p></div>
                        <input v-model="form.include_exclusive_offer" type="checkbox" class="mt-1 size-5 rounded border-slate-300 text-court-700 focus:ring-court-500">
                    </div>
                    <p v-if="campaign?.offer_locked" class="mt-4 rounded-xl bg-amber-100 px-4 py-3 text-sm leading-6 text-amber-950">Offer terms are locked because customers have already received this private offer. You can keep it enabled for future eligible customers or disable future issuance.</p>
                    <div v-if="form.include_exclusive_offer" class="mt-5 grid gap-5 sm:grid-cols-2" :class="{ 'pointer-events-none opacity-60': campaign?.offer_locked }">
                        <label class="block"><span class="text-sm font-medium">Discount type</span><AppSelect v-model="form.offer_discount_type" :options="[{ value: 'fixed', label: 'Fixed amount off' }, { value: 'percentage', label: 'Percentage off' }]" class="mt-2 min-h-12" :disabled="campaign?.offer_locked" /><FormError :message="form.errors.offer_discount_type" /></label>
                        <label class="block"><span class="text-sm font-medium">{{ form.offer_discount_type === 'fixed' ? 'Amount off (PHP)' : 'Percentage off' }}</span><input v-model="form.offer_discount_value" :disabled="campaign?.offer_locked" type="number" min="0.01" step="0.01" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" /><FormError :message="form.errors.offer_discount_value" /></label>
                        <label class="block"><span class="text-sm font-medium">Minimum court subtotal <span class="font-normal text-slate-400">optional PHP</span></span><input v-model="form.offer_minimum_spend" :disabled="campaign?.offer_locked" type="number" min="0" step="0.01" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" /><FormError :message="form.errors.offer_minimum_spend" /></label>
                        <label class="block"><span class="text-sm font-medium">Valid for days</span><input v-model="form.offer_valid_days" :disabled="campaign?.offer_locked" type="number" min="1" max="90" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" /><FormError :message="form.errors.offer_valid_days" /></label>
                        <label class="block sm:col-span-2"><span class="text-sm font-medium">Campaign redemption cap <span class="font-normal text-slate-400">optional</span></span><input v-model="form.offer_max_redemptions" :disabled="campaign?.offer_locked" type="number" min="1" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" /><p class="mt-1 text-xs text-slate-500">Reservations count against this cap until checkout expires, so the cap cannot be oversold.</p><FormError :message="form.errors.offer_max_redemptions" /></label>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold">Message</h3>
                    <label class="mt-5 block"><span class="text-sm font-medium">Title</span><input v-model="form.title" maxlength="120" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" placeholder="Ready for another game?" /><FormError :message="form.errors.title" /></label>
                    <label class="mt-5 block"><span class="text-sm font-medium">Message</span><textarea v-model="form.message" maxlength="500" rows="5" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" placeholder="Your next court is waiting. Check current times at our venue." /><FormError :message="form.errors.message" /></label>
                    <p class="mt-2 text-xs text-slate-400">FinACourt uses each player’s enabled comeback-message channels: in-app, email, or both. SMS and push notifications are not configured.</p>

                    <div class="mt-6 overflow-hidden rounded-3xl border border-emerald-200 bg-emerald-950 shadow-xl shadow-emerald-950/10" aria-label="Comeback email preview">
                        <div class="flex items-center justify-between border-b border-white/10 px-5 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200">
                            <span>Live email preview</span>
                            <span aria-hidden="true">🎉</span>
                        </div>
                        <div class="relative p-6 sm:p-8">
                            <div aria-hidden="true" class="absolute -right-8 -top-8 size-28 rounded-full border-[18px] border-amber-300/20"></div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-300">Your next game awaits</p>
                            <h4 class="relative mt-3 max-w-xl text-2xl font-semibold leading-tight text-white">{{ form.title || 'Ready for another game?' }}</h4>
                            <p class="relative mt-3 max-w-xl whitespace-pre-line text-sm leading-6 text-emerald-100">{{ form.message || 'Your next court is waiting. Check current times and make your comeback.' }}</p>
                            <div class="relative mt-6 inline-flex rounded-xl bg-amber-300 px-5 py-3 text-sm font-bold text-emerald-950 shadow-lg shadow-black/10">Find my next game →</div>
                        </div>
                        <div class="bg-white px-5 py-4 text-xs leading-5 text-slate-500">Players will also see the venue name, any suggested court time, and a link to manage their message preferences.</div>
                    </div>
                </section>
                <div class="flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Saving…' : (campaign ? 'Save changes' : (form.delivery_mode === 'automated' ? 'Activate automation' : 'Save draft')) }}</button></div>
            </form>
        </div>
    </OwnerLayout>
</template>
