<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useBackgroundMap } from '@/composables/useBackgroundMap';
import { t } from '@/lib/i18n';
import { home } from '@/routes';

type Props = {
    status: number;
};

const props = defineProps<Props>();

const page = usePage();
const appName = page.props.name;
const isGuest = computed(() => !page.props.auth?.user);

useBackgroundMap();

const copy = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: t('Forbidden'),
                text: t('You do not have access to this page.'),
            };
        case 404:
            return {
                title: t('Page not found'),
                text: t(
                    'This page does not exist or has been moved somewhere else.',
                ),
            };
        case 429:
            return {
                title: t('Too many requests'),
                text: t('Slow down a little and try again in a moment.'),
            };
        case 503:
            return {
                title: t('Service unavailable'),
                text: t('The site is down for maintenance. Come back shortly.'),
            };
        default:
            return {
                title: t('Server error'),
                text: t(
                    'Something broke on our side. The failure has been logged.',
                ),
            };
    }
});
</script>

<template>
    <Head :title="`${status} - ${copy.title}`" />

    <div
        :class="
            isGuest
                ? 'flex min-h-svh flex-col bg-background px-6 py-6 lg:px-10'
                : 'flex flex-1 flex-col p-4 md:p-8'
        "
    >
        <PublicHeader v-if="isGuest" :app-name="appName" class="mb-10" />

        <div
            class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center"
        >
            <div
                class="wow-panel flex flex-col items-center gap-4 p-8 text-center"
                data-test="error-page"
            >
                <span
                    class="text-gold font-display text-5xl tracking-widest"
                    data-test="error-status"
                >
                    {{ status }}
                </span>
                <h1
                    class="font-display text-lg tracking-wide text-foreground"
                    data-test="error-title"
                >
                    {{ copy.title }}
                </h1>
                <hr class="wow-divider w-full" />
                <p class="text-sm text-muted-foreground">
                    {{ copy.text }}
                </p>
                <Link :href="home()" class="wow-btn mt-2 text-sm">
                    {{ t('Back to home') }}
                </Link>
            </div>
        </div>
    </div>
</template>
