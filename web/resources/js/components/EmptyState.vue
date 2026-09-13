<script setup lang="ts">
import type { Component } from 'vue';

type Props = {
    icon?: Component;
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <div
        class="wow-panel flex flex-col items-center gap-3 px-6 py-12 text-center"
    >
        <component :is="icon" v-if="icon" class="text-gold size-7" />

        <p class="font-display text-lg tracking-wide text-foreground">
            {{ title }}
        </p>

        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>

        <slot />
    </div>
</template>
