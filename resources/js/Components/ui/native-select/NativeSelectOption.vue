<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <option
    data-slot="native-select-option"
    :class="cn('bg-white text-slate-950 dark:bg-slate-950 dark:text-slate-50', props.class)"
  >
    <slot />
  </option>
</template>
