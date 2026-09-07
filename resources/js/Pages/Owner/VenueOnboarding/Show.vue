<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppSelect from '../../../Components/AppSelect.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    onboarding: { type: Object, required: true },
});

const relationships = [
    { value: 'owner', label: 'I own this venue' },
    { value: 'authorized_manager', label: 'I manage this venue' },
    { value: 'authorized_representative', label: 'I represent the owner' },
];

const form = useForm({
    relationship_to_venue: 'owner',
    verification_contact: '',
    evidence_details: '',
    venue_confirmation: false,
});

function submitInvitation() {
    form.post(props.onboarding.invitation.submit_url);
}
</script>

<template>
    <Head title="Venue setup" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <header class="rounded-3xl bg-court-950 px-6 py-8 text-white shadow-sm sm:px-9 sm:py-10">
                <div class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-200">{{ onboarding.source_label }}</p>
                        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                            {{ onboarding.stage === 'complete' ? 'Your venue is ready for players' : 'Let’s get your venue ready' }}
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-court-100 sm:text-base">
                            {{ onboarding.stage === 'complete' ? 'Setup is complete. Use the owner workspace to manage availability, bookings, and growth.' : 'Your progress is saved automatically. Complete the current step now, or return to this page later from Setup progress.' }}
                        </p>
                    </div>
                    <div class="shrink-0 rounded-2xl border border-white/15 bg-white/10 px-5 py-4">
                        <p class="text-xs uppercase tracking-[0.14em] text-court-200">Progress</p>
                        <p class="mt-1 text-2xl font-semibold">{{ onboarding.completed_steps }} of {{ onboarding.total_steps }}</p>
                        <p class="mt-1 text-xs text-court-100">steps completed</p>
                    </div>
                </div>
            </header>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <main>
                    <section v-if="onboarding.stage === 'verify_email'" class="app-card p-6 sm:p-8">
                        <p class="eyebrow">Current step</p>
                        <h2 class="mt-2 text-2xl font-semibold">Verify your account email</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Confirm your email before creating or claiming a venue. This protects your account; venue ownership is checked separately.</p>
                        <Link href="/email/verify" class="mt-6 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Open email verification</Link>
                    </section>

                    <template v-else-if="onboarding.stage === 'confirm_invitation' && onboarding.invitation">
                        <section class="app-card overflow-hidden" aria-labelledby="invited-venue-heading">
                            <div class="border-b border-slate-100 bg-court-50 px-6 py-5">
                                <p class="eyebrow">Current step</p>
                                <h2 id="invited-venue-heading" class="mt-2 text-2xl font-semibold">Review the invited venue</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-600">FinACourt pre-created this public listing. Confirming it starts an independent ownership review; it does not give immediate access.</p>
                            </div>
                            <div class="p-6">
                                <h3 class="text-xl font-semibold">{{ onboarding.invitation.listing.name }}</h3>
                                <p class="mt-2 text-sm text-slate-600">{{ onboarding.invitation.listing.address }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ onboarding.invitation.listing.city }}, {{ onboarding.invitation.listing.province }}</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span v-for="sport in onboarding.invitation.listing.sports" :key="sport" class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ sport }}</span>
                                </div>
                                <p class="mt-5 text-xs text-slate-500">This one-time invitation expires {{ onboarding.invitation.expires_at }}.</p>
                            </div>
                        </section>

                        <form class="app-card mt-5 space-y-5 p-6 sm:p-8" @submit.prevent="submitInvitation">
                            <div>
                                <p class="eyebrow">Ownership confirmation</p>
                                <h2 class="mt-2 text-xl font-semibold">Tell us how you are connected</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-500">Do not paste private identity documents or sensitive numbers into these fields.</p>
                            </div>

                            <div v-if="form.errors.listing" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ form.errors.listing }}</div>

                            <label class="block text-sm font-semibold text-slate-700">
                                Your role at this venue
                                <AppSelect v-model="form.relationship_to_venue" :options="relationships" class="mt-2" />
                                <span v-if="form.errors.relationship_to_venue" class="mt-1 block text-xs text-red-600">{{ form.errors.relationship_to_venue }}</span>
                            </label>

                            <label class="block text-sm font-semibold text-slate-700">
                                Contact for verification
                                <input v-model="form.verification_contact" required maxlength="160" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="A business email or phone number where FinACourt can reach you">
                                <span v-if="form.errors.verification_contact" class="mt-1 block text-xs text-red-600">{{ form.errors.verification_contact }}</span>
                            </label>

                            <label class="block text-sm font-semibold text-slate-700">
                                How can FinACourt verify your connection?
                                <textarea v-model="form.evidence_details" required minlength="30" maxlength="3000" rows="6" class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3 font-normal" placeholder="For example: official website, public venue email, business registration, lease, phone call, or an in-person visit."></textarea>
                                <span v-if="form.errors.evidence_details" class="mt-1 block text-xs text-red-600">{{ form.errors.evidence_details }}</span>
                            </label>

                            <div class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                                Your FinACourt account email is already verified, so no additional code is required. A platform administrator still checks the venue through an independent source before approval.
                            </div>

                            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 text-sm leading-6 text-slate-700">
                                <input v-model="form.venue_confirmation" required type="checkbox" class="mt-1 size-4 shrink-0 accent-court-700">
                                <span>
                                    I confirm that this listing is the venue I own, manage, or am authorized to represent. I understand that this starts a verification review and does not give immediate access.
                                    <span v-if="form.errors.venue_confirmation" class="mt-1 block text-xs text-red-600">{{ form.errors.venue_confirmation }}</span>
                                </span>
                            </label>

                            <button :disabled="form.processing" class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50">
                                {{ form.processing ? 'Submitting confirmation…' : 'Submit ownership confirmation' }}
                            </button>
                        </form>
                    </template>

                    <section v-else-if="onboarding.stage === 'ownership_review'" class="app-card p-6 sm:p-8">
                        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">Independent check in progress</span>
                        <h2 class="mt-4 text-2xl font-semibold">FinACourt is reviewing your ownership request</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">We received your confirmation for <strong>{{ onboarding.claim?.listing?.name || onboarding.venue?.name }}</strong>. Owner tools unlock only after a platform administrator completes an independent check.</p>
                        <dl class="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2">
                            <div><dt class="text-slate-500">Request status</dt><dd class="mt-1 font-semibold">{{ onboarding.claim?.status_label || onboarding.application?.status_label }}</dd></div>
                            <div><dt class="text-slate-500">Submitted</dt><dd class="mt-1 font-semibold">{{ onboarding.claim?.created_at || onboarding.application?.submitted_at }}</dd></div>
                        </dl>
                        <Link v-if="onboarding.claim" href="/owner/directory-claims" class="mt-6 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">View request details</Link>
                    </section>

                    <section v-else-if="onboarding.stage === 'claim_attention'" class="app-card p-6 sm:p-8">
                        <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Action needed</span>
                        <h2 class="mt-4 text-2xl font-semibold">This ownership request needs attention</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ onboarding.claim?.review_notes || 'Review the request details before choosing your next step.' }}</p>
                        <Link href="/owner/directory-claims" class="mt-6 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Review venue request</Link>
                    </section>

                    <section v-else-if="onboarding.stage === 'application_attention'" class="app-card p-6 sm:p-8">
                        <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Changes required</span>
                        <h2 class="mt-4 text-2xl font-semibold">Update your venue application</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">FinACourt could not approve <strong>{{ onboarding.venue?.name }}</strong> yet. Correct the venue information and save it to resubmit the application.</p>
                        <div class="mt-5 rounded-xl border border-red-100 bg-red-50 p-4 text-sm leading-6 text-red-800">
                            <strong>Review note:</strong> {{ onboarding.application?.review_notes || 'Contact FinACourt for the specific information needed.' }}
                        </div>
                        <Link :href="onboarding.venue?.edit_url" class="mt-6 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Correct and resubmit</Link>
                    </section>

                    <section v-else-if="onboarding.stage === 'choose_venue'" class="app-card p-6 sm:p-8">
                        <p class="eyebrow">Current step</p>
                        <h2 class="mt-2 text-2xl font-semibold">Find your venue before adding it</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Search the public venue guide first. This prevents duplicate listings and keeps existing venue information connected to the right owner.</p>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-court-200 bg-court-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-court-700">Recommended first</p>
                                <h3 class="mt-2 text-lg font-semibold">Search the venue guide</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">If your venue is already listed, ask FinACourt for its secure private invitation instead of creating a duplicate.</p>
                                <a href="/directory" class="mt-5 inline-flex rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Search existing venues</a>
                            </div>
                            <div class="rounded-2xl border border-slate-200 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Not listed?</p>
                                <h3 class="mt-2 text-lg font-semibold">Add a new venue</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Submit the venue details for an ownership check. Private court setup unlocks after FinACourt approves it.</p>
                                <Link href="/owner/venues/create?onboarding=1" class="mt-5 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Start venue application</Link>
                            </div>
                        </div>
                    </section>

                    <section v-else-if="onboarding.stage === 'add_court'" class="app-card p-6 sm:p-8">
                        <p class="eyebrow">Current step</p>
                        <h2 class="mt-2 text-2xl font-semibold">Add the first bookable court</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600"><strong>{{ onboarding.venue?.name }}</strong> is saved. Add a court with its sport, normal hourly price, booking duration, and active status.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link :href="onboarding.venue.add_court_url" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Add a court</Link>
                            <Link :href="onboarding.venue.edit_url" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Review venue details</Link>
                        </div>
                    </section>

                    <section v-else-if="onboarding.stage === 'request_publication'" class="app-card p-6 sm:p-8">
                        <p class="eyebrow">Current step</p>
                        <h2 class="mt-2 text-2xl font-semibold">{{ onboarding.venue?.requires_platform_review ? 'Finish setup and request publication' : 'Review and publish your venue' }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600" v-if="onboarding.venue?.requires_platform_review">Your venue has bookable inventory. Select “Show this venue to players” and save it to request FinACourt’s final marketplace check.</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600" v-else>Check the public details, opening hours, and court price. When ready, select “Show this venue to players” and save.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link :href="onboarding.venue.edit_url" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Review publication settings</Link>
                            <Link :href="onboarding.venue.show_url" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Open venue workspace</Link>
                        </div>
                    </section>

                    <section v-else-if="onboarding.stage === 'marketplace_review'" class="app-card p-6 sm:p-8">
                        <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">Final check requested</span>
                        <h2 class="mt-4 text-2xl font-semibold">Your venue is waiting for FinACourt’s final check</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Your setup for <strong>{{ onboarding.venue?.name }}</strong> is saved, but the venue remains private until a platform administrator approves it for the marketplace.</p>
                        <p v-if="onboarding.venue?.marketplace_review_requested_at" class="mt-3 text-xs text-slate-500">Requested {{ onboarding.venue.marketplace_review_requested_at }}</p>
                        <Link :href="onboarding.venue.show_url" class="mt-6 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Open venue workspace</Link>
                    </section>

                    <section v-else class="app-card p-6 sm:p-8">
                        <span class="inline-flex rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">Setup complete</span>
                        <h2 class="mt-4 text-2xl font-semibold">{{ onboarding.venue?.name }} is ready for players</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">You can now manage availability and bookings from the owner workspace.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a v-if="onboarding.venue?.public_url" :href="onboarding.venue.public_url" class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">View public venue</a>
                            <Link href="/owner/dashboard" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700">Go to owner dashboard</Link>
                        </div>
                    </section>
                </main>

                <aside class="app-card self-start overflow-hidden lg:sticky lg:top-6" aria-labelledby="setup-progress-heading">
                    <div class="border-b border-slate-100 px-5 py-5">
                        <h2 id="setup-progress-heading" class="text-lg font-semibold">Setup progress</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">The next step changes automatically as your venue moves forward.</p>
                    </div>
                    <ol class="divide-y divide-slate-100">
                        <li v-for="(step, index) in onboarding.steps" :key="step.key" class="flex gap-3 px-5 py-4" :aria-current="step.state === 'current' ? 'step' : undefined">
                            <span :class="[
                                'mt-0.5 grid size-7 shrink-0 place-items-center rounded-full text-xs font-semibold',
                                step.state === 'complete' ? 'bg-court-700 text-white' : (step.state === 'current' ? 'bg-amber-100 text-amber-900 ring-2 ring-amber-200' : 'bg-slate-100 text-slate-400'),
                            ]">{{ step.state === 'complete' ? '✓' : index + 1 }}</span>
                            <div>
                                <p :class="['text-sm font-semibold', step.state === 'upcoming' ? 'text-slate-400' : 'text-slate-800']">{{ step.label }}</p>
                                <p :class="['mt-1 text-xs leading-5', step.state === 'upcoming' ? 'text-slate-400' : 'text-slate-500']">{{ step.description }}</p>
                            </div>
                        </li>
                    </ol>
                </aside>
            </div>
        </div>
    </OwnerLayout>
</template>
