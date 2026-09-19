<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import FormError from '../../../Components/FormError.vue';
import OwnerLayout from '../../../Layouts/OwnerLayout.vue';

const props = defineProps({
    staff: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    permissionOptions: { type: Array, default: () => [] },
    timezone: String,
});

const inviteForm = useForm({ email: '', permissions: [] });
const memberPermissions = reactive(Object.fromEntries(
    props.staff.map((member) => [member.id, [...member.permissions]]),
));

function toggleInvitePermission(permission) {
    inviteForm.permissions = inviteForm.permissions.includes(permission)
        ? inviteForm.permissions.filter((value) => value !== permission)
        : [...inviteForm.permissions, permission];
}

function toggleMemberPermission(memberId, permission) {
    const selected = memberPermissions[memberId] || [];
    memberPermissions[memberId] = selected.includes(permission)
        ? selected.filter((value) => value !== permission)
        : [...selected, permission];
}

function sendInvitation() {
    inviteForm.post('/owner/team/invitations', {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset(),
    });
}

function savePermissions(member) {
    router.patch(`/owner/team/${member.id}`, {
        permissions: memberPermissions[member.id] || [],
    }, { preserveScroll: true });
}

function suspend(member) {
    if (window.confirm(`Suspend ${member.name}'s access? They will be blocked from the owner workspace immediately.`)) {
        router.patch(`/owner/team/${member.id}/suspend`, {}, { preserveScroll: true });
    }
}

function reactivate(member) {
    router.patch(`/owner/team/${member.id}/reactivate`, {}, { preserveScroll: true });
}

function remove(member) {
    if (window.confirm(`Remove ${member.name} from this team? They will lose all organization access.`)) {
        router.delete(`/owner/team/${member.id}`, { preserveScroll: true });
    }
}

function resend(invitation) {
    router.post(`/owner/team/invitations/${invitation.id}/resend`, {}, { preserveScroll: true });
}

function revoke(invitation) {
    if (window.confirm(`Revoke the invitation for ${invitation.email}?`)) {
        router.delete(`/owner/team/invitations/${invitation.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Team and staff" />
    <OwnerLayout>
        <div class="mx-auto max-w-6xl">
            <div>
                <p class="eyebrow">Owner controls</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Team and staff</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Invite trusted staff and give each person only the access they need. Team administration, court earnings, payout details, and payout requests remain owner-only.</p>
            </div>

            <div class="mt-8 grid gap-6 xl:grid-cols-[22rem_1fr]">
                <aside class="xl:sticky xl:top-6 xl:self-start">
                    <form class="app-card p-6" @submit.prevent="sendInvitation">
                        <h2 class="text-lg font-semibold text-slate-950">Invite staff</h2>
                        <p class="mt-1 text-sm leading-6 text-slate-500">We will email a secure, single-use link that expires after seven days.</p>

                        <label class="mt-5 block">
                            <span class="text-sm font-medium text-slate-700">Email address</span>
                            <input v-model="inviteForm.email" type="email" required autocomplete="email" placeholder="staff@example.com" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5">
                            <FormError :message="inviteForm.errors.email" />
                        </label>

                        <fieldset class="mt-5">
                            <legend class="text-sm font-medium text-slate-700">Optional permissions</legend>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Dashboard access is always included.</p>
                            <label v-for="permission in permissionOptions" :key="permission.value" class="mt-3 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3">
                                <input type="checkbox" :checked="inviteForm.permissions.includes(permission.value)" class="mt-1 size-4 rounded border-slate-300 text-court-700" @change="toggleInvitePermission(permission.value)">
                                <span><strong class="block text-sm text-slate-800">{{ permission.label }}</strong><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ permission.description }}</span></span>
                            </label>
                            <FormError :message="inviteForm.errors.permissions" />
                        </fieldset>

                        <button type="submit" :disabled="inviteForm.processing" class="mt-6 w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ inviteForm.processing ? 'Sending…' : 'Send staff invitation' }}</button>
                    </form>
                </aside>

                <div class="space-y-6">
                    <section class="app-card overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-5">
                            <h2 class="text-lg font-semibold text-slate-950">Current staff <span class="text-slate-400">({{ staff.length }})</span></h2>
                        </div>

                        <div v-if="staff.length" class="divide-y divide-slate-100">
                            <article v-for="member in staff" :key="member.id" class="p-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-slate-950">{{ member.name }}</h3>
                                            <span :class="member.status === 'active' ? 'bg-court-50 text-court-800' : 'bg-amber-50 text-amber-800'" class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize">{{ member.status }}</span>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-500">{{ member.email }} · joined {{ member.joined_at }}</p>
                                        <p v-if="member.suspended_at" class="mt-1 text-xs text-amber-700">Suspended {{ member.suspended_at }}<span v-if="member.suspended_by"> by {{ member.suspended_by }}</span></p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button v-if="member.status === 'active'" type="button" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800" @click="suspend(member)">Suspend</button>
                                        <button v-else type="button" class="rounded-lg border border-court-300 px-3 py-2 text-xs font-semibold text-court-800" @click="reactivate(member)">Restore access</button>
                                        <button type="button" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700" @click="remove(member)">Remove</button>
                                    </div>
                                </div>

                                <fieldset class="mt-5 rounded-xl bg-slate-50 p-4" :disabled="member.status !== 'active'">
                                    <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-slate-500">Permissions</legend>
                                    <p class="mb-3 text-xs text-slate-500">Dashboard access is always included.</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label v-for="permission in permissionOptions" :key="permission.value" class="flex items-start gap-3 text-sm">
                                            <input type="checkbox" :checked="memberPermissions[member.id]?.includes(permission.value)" class="mt-1 size-4 rounded border-slate-300 text-court-700" @change="toggleMemberPermission(member.id, permission.value)">
                                            <span><strong class="block text-slate-800">{{ permission.label }}</strong><span class="text-xs leading-5 text-slate-500">{{ permission.description }}</span></span>
                                        </label>
                                    </div>
                                    <button type="button" class="mt-4 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-court-800 shadow-sm ring-1 ring-slate-200 disabled:opacity-50" :disabled="member.status !== 'active'" @click="savePermissions(member)">Save permissions</button>
                                </fieldset>
                            </article>
                        </div>
                        <div v-else class="px-6 py-12 text-center"><p class="font-semibold text-slate-800">No staff members yet</p><p class="mt-2 text-sm text-slate-500">Send an invitation when someone needs access to help run your courts.</p></div>
                    </section>

                    <section class="app-card overflow-hidden">
                        <div class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-semibold text-slate-950">Invitations <span class="text-slate-400">({{ invitations.length }})</span></h2></div>
                        <div v-if="invitations.length" class="divide-y divide-slate-100">
                            <article v-for="invitation in invitations" :key="invitation.id" class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                                <div><div class="flex flex-wrap items-center gap-2"><strong class="text-sm text-slate-900">{{ invitation.email }}</strong><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">{{ invitation.status_label }}</span></div><p class="mt-1 text-xs text-slate-500">Sent {{ invitation.last_sent_at }} · expires {{ invitation.expires_at }}</p></div>
                                <div class="flex gap-2"><button v-if="invitation.can_resend" type="button" class="rounded-lg border border-court-300 px-3 py-2 text-xs font-semibold text-court-800" @click="resend(invitation)">Resend</button><button v-if="invitation.can_revoke" type="button" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700" @click="revoke(invitation)">Revoke</button></div>
                            </article>
                        </div>
                        <div v-else class="px-6 py-10 text-center text-sm text-slate-500">No pending, expired, or revoked invitations.</div>
                    </section>
                </div>
            </div>
        </div>
    </OwnerLayout>
</template>
