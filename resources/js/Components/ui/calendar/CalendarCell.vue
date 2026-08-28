<script setup>
import { reactiveOmit } from "@vueuse/core";
import { CalendarCell, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  date: { type: Object, required: true },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <CalendarCell
    data-slot="calendar-cell"
    :class="
      cn(
        'relative flex-1 p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([data-selected])]:rounded-lg [&:has([data-selected])]:bg-court-50',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCell>
</template>
