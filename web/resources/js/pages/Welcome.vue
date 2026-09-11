<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AddonDownloadButton from '@/components/AddonDownloadButton.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { t } from '@/lib/i18n';
import { login, register } from '@/routes';
import type { AddonDownload } from '@/types';

type Props = {
    addon: AddonDownload;
};

defineProps<Props>();

const page = usePage();
const appName = page.props.name;

const steps = [
    {
        title: t('Install the addon'),
        text: t(
            'Unpack the archive into Interface/AddOns. The folder must be named WhereIveBeen.',
        ),
    },
    {
        title: t('Play as usual'),
        text: t(
            'The addon records your position and in-game events while you play. Nothing to configure.',
        ),
    },
    {
        title: t('Import the log'),
        text: t(
            'Export a session from the addon window and paste it on the Imports page.',
        ),
    },
];

const clients = [
    t('Vanilla'),
    t('TBC'),
    t('Wrath'),
    t('Cataclysm'),
    t('Mists'),
    t('Retail'),
];
</script>

<template>
    <Head :title="t('Welcome')" />

    <div class="flex min-h-svh flex-col bg-background px-6 py-6 lg:px-10">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="wow-frame flex size-11 items-center justify-center bg-sidebar"
                >
                    <AppLogoIcon class="text-gold size-6 fill-current" />
                </div>
                <span class="text-gold font-display text-lg tracking-[0.22em]">
                    {{ appName }}
                </span>
            </div>

            <nav class="flex items-center gap-3">
                <Link href="/support" class="wow-btn-ghost">
                    {{ t('Support') }}
                </Link>
                <Link :href="login()" class="wow-btn-ghost">
                    {{ t('Log in') }}
                </Link>
                <Link :href="register()" class="wow-btn">
                    {{ t('Sign up') }}
                </Link>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center py-14"
        >
            <h1 class="wow-title text-4xl font-black sm:text-5xl">
                {{ appName }}
            </h1>

            <hr class="wow-divider-ornate my-7 max-w-md" />

            <p class="max-w-2xl text-base leading-relaxed text-foreground/90">
                {{
                    t(
                        'A route tracker for World of Warcraft. An addon records where your character goes and what happens along the way — mounts, flight paths, deaths, levels, loot, quests. You export the session, import it here, and the route is drawn on the zone map with every event marked on it.',
                    )
                }}
            </p>

            <div class="mt-8">
                <AddonDownloadButton :addon="addon" />
            </div>

            <div class="mt-14 grid gap-4 md:grid-cols-3">
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="wow-panel flex flex-col gap-2 p-5"
                >
                    <span
                        class="text-xs tracking-[0.3em] text-muted-foreground uppercase"
                    >
                        {{ t('Step :number', { number: index + 1 }) }}
                    </span>
                    <h2 class="text-gold font-display tracking-wide">
                        {{ step.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">{{ step.text }}</p>
                </div>
            </div>
        </main>

        <footer
            class="mx-auto flex w-full max-w-5xl flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted-foreground"
        >
            <span class="tracking-wide uppercase">
                {{ t('Supported clients') }}
            </span>
            <span class="h-3 w-px bg-border" />
            <span v-for="client in clients" :key="client">{{ client }}</span>
        </footer>
    </div>
</template>
