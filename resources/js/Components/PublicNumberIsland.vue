<script setup>
import { ref } from 'vue';
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from './ui/number-field';
import { optionalFiniteNumber } from '../lib/numbers';

const props = defineProps({
    config: { type: Object, required: true },
});

const value = ref(optionalFiniteNumber(props.config.value));
const minimum = optionalFiniteNumber(props.config.min);
const maximum = optionalFiniteNumber(props.config.max);
const step = optionalFiniteNumber(props.config.step) ?? 1;

function preserveValueOnWheel(event) {
    // Keep wheel gestures available to the surrounding filter panel without
    // allowing the focused number field to interpret them as value changes.
    event.stopPropagation();
}
</script>

<template>
    <NumberField
        v-model="value"
        :name="config.name"
        :min="minimum"
        :max="maximum"
        :step="step"
        :disabled="config.disabled"
        :id="config.id"
        locale="en-PH"
        :disable-wheel-change="true"
        :format-options="{
            style: 'currency',
            currency: 'PHP',
            currencyDisplay: 'narrowSymbol',
            maximumFractionDigits: 2,
        }"
    >
        <NumberFieldContent @wheel.capture="preserveValueOnWheel">
            <NumberFieldDecrement :aria-label="`Decrease ${config.ariaLabel}`" />
            <NumberFieldInput :placeholder="config.placeholder" :aria-label="config.ariaLabel" />
            <NumberFieldIncrement :aria-label="`Increase ${config.ariaLabel}`" />
        </NumberFieldContent>
    </NumberField>
</template>
