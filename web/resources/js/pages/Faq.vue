<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { t } from '@/lib/i18n';
import type { FaqItem } from '@/types';

type Props = {
    heading: string;
    intro: string;
    items: FaqItem[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('FAQ'), href: '/faq' }],
    },
});

const page = usePage();
const appName = page.props.name;
const meta = computed(() => page.props.meta);
const isGuest = computed(() => !page.props.auth?.user);
</script>

<template>
    <Head :title="meta.title" />

    <div
        :class="
            isGuest
                ? 'flex min-h-svh flex-col bg-background px-6 py-6 lg:px-10'
                : 'flex flex-1 flex-col p-4 md:p-8'
        "
    >
        <PublicHeader v-if="isGuest" :app-name="appName" class="mb-10" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <div class="wow-panel flex flex-col gap-3 p-6">
                <h1 class="text-gold font-display text-lg tracking-wide">
                    {{ heading }}
                </h1>
                <hr class="wow-divider" />
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ intro }}
                </p>
            </div>

            <div
                v-for="item in items"
                :key="item.question"
                class="wow-panel flex flex-col gap-2 p-6"
            >
                <h2 class="text-gold font-display tracking-wide">
                    {{ item.question }}
                </h2>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ item.answer }}
                </p>
            </div>
        </div>
    </div>
</template>
