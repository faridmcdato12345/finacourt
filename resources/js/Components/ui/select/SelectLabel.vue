<script setup>
import { SelectLabel } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  for: { type: String, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <SelectLabel
    data-slot="select-label"
    :class="cn('text-slate-500 px-2 py-1.5 text-xs dark:text-slate-400', props.class)"
  >
    <slot />
  </SelectLabel>
</template>
