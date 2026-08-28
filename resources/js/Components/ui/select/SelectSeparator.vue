<script setup>
import { reactiveOmit } from "@vueuse/core";
import { SelectSeparator } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <SelectSeparator
    data-slot="select-separator"
    v-bind="delegatedProps"
    :class="cn('bg-slate-200 pointer-events-none -mx-1 my-1 h-px dark:bg-slate-800', props.class)"
  />
</template>
