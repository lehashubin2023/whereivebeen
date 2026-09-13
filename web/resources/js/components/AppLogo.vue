<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center">
        <AppLogoIcon class="text-gold size-7" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span
            class="truncate font-mono text-[0.78rem] leading-tight font-medium tracking-[0.18em] text-foreground uppercase"
            >{{ name }}</span
        >
    </div>
</template>
