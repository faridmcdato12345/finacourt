<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppSelect from './AppSelect.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const selectedValue = ref(props.config.value ?? '');
const islandRoot = ref(null);
const isDisabled = ref(Boolean(props.config.disabled));
let disableListener;
const triggerClass = computed(() => {
    if (props.config.variant === 'hero-slim') {
        return 'rounded-xl border-0 bg-transparent px-0 py-0 text-sm font-semibold shadow-none hover:border-transparent hover:bg-court-50 focus-visible:border-transparent focus-visible:ring-0 lg:text-xs';
    }

    if (props.config.variant === 'quiet') {
        return 'h-9 rounded-xl border-0 bg-transparent px-0 py-0 font-semibold shadow-none hover:border-transparent hover:bg-court-50 focus-visible:border-transparent focus-visible:ring-0';
    }

    if (props.config.variant === 'compact') {
        return 'h-10 rounded-xl border-slate-200 bg-slate-50/70 px-3 py-2 shadow-none hover:bg-white';
    }

    return 'h-12 rounded-2xl border-slate-200 bg-gradient-to-b from-white to-slate-50/80 px-4 shadow-[0_8px_24px_rgba(15,23,42,0.06)] hover:border-court-300 hover:bg-white';
});

function publishValue(value = selectedValue.value) {
    if (!islandRoot.value) return;

    islandRoot.value.dataset.publicSelectValue = String(value ?? '');
    islandRoot.value.dispatchEvent(new CustomEvent('public-select:change', {
        bubbles: true,
        detail: { name: props.config.name, value },
    }));
}

onMounted(() => {
    publishValue();
    disableListener = (event) => {
        isDisabled.value = Boolean(event.detail?.disabled);
    };
    islandRoot.value?.addEventListener('public-select:set-disabled', disableListener);
});

onBeforeUnmount(() => {
    islandRoot.value?.removeEventListener('public-select:set-disabled', disableListener);
});

async function selectionChanged(value) {
    publishValue(value);
    if (!props.config.submitOnChange) return;

    await nextTick();

    const form = islandRoot.value?.closest('form');

    if (!form) return;

    form.querySelectorAll('[data-reset-on-select-change]').forEach((control) => {
        control.disabled = true;
    });
    const availability = form.closest('[data-live-availability]');
    availability?.setAttribute('aria-busy', 'true');
    availability?.querySelector('[data-availability-results]')?.classList.add('pointer-events-none', 'opacity-45');
    form.requestSubmit();
}
</script>

<template>
    <div ref="islandRoot">
        <AppSelect
            v-model="selectedValue"
            :options="config.options"
            :name="isDisabled ? undefined : config.name"
            :disabled="isDisabled"
            :placeholder="config.placeholder"
            :aria-label="config.ariaLabel"
            :size="config.variant === 'hero-slim' ? 'sm' : 'default'"
            :class="triggerClass"
            content-class="max-h-80 rounded-2xl border-court-100 bg-white/98 p-1 shadow-[0_22px_60px_rgba(8,41,30,0.18)] backdrop-blur-xl"
            @change="selectionChanged"
        />
    </div>
</template>
