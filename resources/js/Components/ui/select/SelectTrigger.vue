<script setup>
import { ChevronDown } from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import { SelectIcon, SelectTrigger, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  disabled: { type: Boolean, required: false },
  reference: { type: null, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  size: { type: String, required: false, default: "default" },
});

const delegatedProps = reactiveOmit(props, "class", "size");
const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <SelectTrigger
    data-slot="select-trigger"
    :data-size="size"
    v-bind="forwardedProps"
    :class="
      cn(
        `flex w-fit items-center justify-between gap-2 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm outline-none transition data-[placeholder]:text-slate-400 hover:border-slate-400 focus-visible:border-court-600 focus-visible:ring-4 focus-visible:ring-court-100 aria-invalid:border-red-500 aria-invalid:ring-red-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400 disabled:opacity-100 data-[size=default]:h-12 data-[size=sm]:h-9 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4`,
        props.class,
      )
    "
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="size-4 text-court-700" />
    </SelectIcon>
  </SelectTrigger>
</template>
