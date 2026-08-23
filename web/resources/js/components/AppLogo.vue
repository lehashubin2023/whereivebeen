<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="wow-frame flex aspect-square size-8 items-center justify-center bg-sidebar"
    >
        <AppLogoIcon class="text-gold size-5 fill-current" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="text-gold mb-0.5 truncate font-display text-sm leading-tight font-semibold tracking-[0.14em]"
            >{{ name }}</span
        >
    </div>
</template>
