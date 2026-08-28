<script setup>
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: 'Sign out' },
});

const signingOut = ref(false);
const csrfToken = typeof document === 'undefined'
    ? ''
    : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
</script>

<template>
    <form action="/logout" method="post" class="contents" @submit="signingOut = true">
        <input type="hidden" name="_token" :value="csrfToken">
        <button
            v-bind="$attrs"
            type="submit"
            :disabled="signingOut"
            :aria-busy="signingOut ? 'true' : undefined"
        >
            {{ signingOut ? 'Signing out...' : props.label }}
        </button>
    </form>
</template>
