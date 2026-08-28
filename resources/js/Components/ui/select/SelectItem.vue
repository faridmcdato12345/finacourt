<script setup>
import { Check } from "@lucide/vue";
import { reactiveOmit } from "@vueuse/core";
import {
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  useForwardProps,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps({
  value: { type: null, required: true },
  disabled: { type: Boolean, required: false },
  textValue: { type: String, required: false },
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
  <SelectItem
    data-slot="select-item"
    v-bind="forwardedProps"
    :class="
      cn(
        `relative flex w-full cursor-pointer items-center gap-2 rounded-lg py-2.5 pr-9 pl-3 text-sm text-slate-700 outline-none select-none focus:bg-court-50 focus:text-court-950 data-[highlighted]:bg-court-50 data-[highlighted]:text-court-950 data-[disabled]:pointer-events-none data-[disabled]:cursor-not-allowed data-[disabled]:opacity-40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 *:[span]:last:flex *:[span]:last:items-center *:[span]:last:gap-2`,
        props.class,
      )
    "
  >
    <span class="absolute right-3 flex size-3.5 items-center justify-center text-court-700">
      <SelectItemIndicator>
        <slot name="indicator-icon">
          <Check class="size-4" />
        </slot>
      </SelectItemIndicator>
    </span>

    <SelectItemText>
      <slot />
    </SelectItemText>
  </SelectItem>
</template>
