<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { locale } from '@/lib/i18n';

const page = usePage();

const alternates = computed(() =>
    Object.entries(page.props.meta.alternates)
        .filter(([code]) => code !== 'x-default')
        .map(([code, href]) => ({ code, href })),
);
</script>

<template>
    <div v-if="alternates.length > 1" class="flex items-center gap-1 text-xs">
        <template
            v-for="(alternate, index) in alternates"
            :key="alternate.code"
        >
            <span v-if="index > 0" class="text-muted-foreground/50">/</span>
            <a
                :href="alternate.href"
                :hreflang="alternate.code"
                :class="[
                    'px-1 font-mono tracking-widest uppercase',
                    alternate.code === locale
                        ? 'text-gold'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                {{ alternate.code }}
            </a>
        </template>
    </div>
</template>
