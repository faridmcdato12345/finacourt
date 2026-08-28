<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppSelect from '../../../Components/AppSelect.vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({ venues: Array, segments: Array, defaults: Object });
const form = useForm({ venue_id: props.defaults?.venue_id || props.venues[0]?.id || '', sport_id: '', title: '', message: '', segment: props.defaults?.segment || 'inactive_30' });
const selectedVenue = computed(() => props.venues.find((venue) => String(venue.id) === String(form.venue_id)));
const sports = computed(() => [{ id: '', name: 'Any sport offered at this venue' }, ...(selectedVenue.value?.sports || [])]);

function venueChanged() { form.sport_id = ''; }
function submit() { form.post('/owner/reactivation'); }
</script>

<template>
    <Head title="Message past players" />
    <OwnerLayout>
        <div class="mx-auto max-w-3xl">
            <Link href="/owner/reactivation" class="text-sm font-semibold text-court-700">← Past players</Link>
            <h2 class="mt-5 text-3xl font-semibold tracking-tight">Message past players</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">Saving will not send anything yet. You can review the message first, then send it from the next page.</p>
            <form class="mt-7 space-y-6" @submit.prevent="submit">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold">Who should receive it?</h3>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="block"><span class="text-sm font-medium">Venue</span><AppSelect v-model="form.venue_id" :options="venues" option-value="id" option-label="name" class="mt-2 min-h-12" @change="venueChanged" /><FormError :message="form.errors.venue_id" /></label>
                        <label class="block"><span class="text-sm font-medium">Player group</span><AppSelect v-model="form.segment" :options="segments" class="mt-2 min-h-12" /><FormError :message="form.errors.segment" /></label>
                        <label class="block sm:col-span-2"><span class="text-sm font-medium">Sport <span class="font-normal text-slate-400">optional</span></span><AppSelect v-model="form.sport_id" :options="sports" option-value="id" option-label="name" class="mt-2 min-h-12" /><FormError :message="form.errors.sport_id" /></label>
                    </div>
                </section>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h3 class="text-lg font-semibold">Message</h3>
                    <label class="mt-5 block"><span class="text-sm font-medium">Title</span><input v-model="form.title" maxlength="120" class="mt-2 min-h-12 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" placeholder="Ready for another game?" /><FormError :message="form.errors.title" /></label>
                    <label class="mt-5 block"><span class="text-sm font-medium">Message</span><textarea v-model="form.message" maxlength="500" rows="5" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-court-500 focus:ring-4 focus:ring-court-100" placeholder="Your next court is waiting. Check current times at our venue." /><FormError :message="form.errors.message" /></label>
                    <p class="mt-2 text-xs text-slate-400">In-app only for now. FinACourt will not send email, SMS, or push notifications unless those are set up later.</p>
                </section>
                <div class="flex justify-end"><button :disabled="form.processing" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{{ form.processing ? 'Saving…' : 'Save draft' }}</button></div>
            </form>
        </div>
    </OwnerLayout>
</template>
