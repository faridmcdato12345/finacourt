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
  <optgroup
    data-slot="native-select-optgroup"
    :class="cn('bg-white text-slate-950 dark:bg-slate-950 dark:text-slate-50', props.class)"
  >
    <slot />
  </optgroup>
</template>
