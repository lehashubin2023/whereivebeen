<script setup lang="ts">
import { useId } from 'vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
    compact?: boolean;
};

withDefaults(defineProps<Props>(), {
    compact: false,
});

const maskId = `wivb-trail-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <mask :id="maskId">
            <rect width="32" height="32" fill="#fff" />
            <template v-if="compact">
                <circle cx="5" cy="7" r="5" fill="#000" />
                <circle cx="16" cy="13" r="4.2" fill="#000" />
                <circle cx="27" cy="7" r="5" fill="#000" />
            </template>
            <template v-else>
                <circle cx="5" cy="7" r="4.4" fill="#000" />
                <circle cx="11.5" cy="25" r="3.6" fill="#000" />
                <circle cx="16" cy="13" r="3.6" fill="#000" />
                <circle cx="20.5" cy="25" r="3.6" fill="#000" />
                <circle cx="27" cy="7" r="4.4" fill="#000" />
            </template>
        </mask>

        <polyline
            points="5,7 11.5,25 16,13 20.5,25 27,7"
            :mask="`url(#${maskId})`"
            stroke="currentColor"
            :stroke-width="compact ? 3 : 2.2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />

        <template v-if="compact">
            <circle
                cx="5"
                cy="7"
                r="3.1"
                stroke="currentColor"
                stroke-width="2.6"
            />
            <circle
                cx="16"
                cy="13"
                r="2.5"
                stroke="currentColor"
                stroke-width="2.4"
            />
            <circle
                cx="27"
                cy="7"
                r="3.1"
                stroke="currentColor"
                stroke-width="2.6"
            />
        </template>
        <template v-else>
            <circle
                cx="5"
                cy="7"
                r="3"
                stroke="currentColor"
                stroke-width="2"
            />
            <circle
                cx="27"
                cy="7"
                r="3"
                stroke="currentColor"
                stroke-width="2"
            />
            <circle
                cx="11.5"
                cy="25"
                r="2.2"
                stroke="currentColor"
                stroke-width="1.8"
            />
            <circle
                cx="16"
                cy="13"
                r="2.2"
                stroke="currentColor"
                stroke-width="1.8"
            />
            <circle
                cx="20.5"
                cy="25"
                r="2.2"
                stroke="currentColor"
                stroke-width="1.8"
            />
        </template>
    </svg>
</template>
