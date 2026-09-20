<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AddonDownloadButton from '@/components/AddonDownloadButton.vue';
import ProductShot from '@/components/ProductShot.vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { useBackgroundMap } from '@/composables/useBackgroundMap';
import { t } from '@/lib/i18n';
import { faq } from '@/routes';
import type { AddonDownload } from '@/types';

type Props = {
    addon: AddonDownload;
};

defineProps<Props>();

const page = usePage();
const appName = page.props.name;
const meta = computed(() => page.props.meta);

useBackgroundMap();

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
    <Head :title="meta.title" />

    <div
        class="page-bg flex min-h-svh flex-col bg-background px-6 py-6 lg:px-10"
    >
        <PublicHeader :app-name="appName" />

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

            <div class="mt-8 flex flex-wrap items-center gap-4">
                <AddonDownloadButton :addon="addon" />
                <Link :href="faq()" class="wow-btn-ghost">
                    {{ t('Read the FAQ') }}
                </Link>
            </div>

            <div class="mt-14 grid gap-8">
                <ProductShot
                    src="/screenshots/session-map.png"
                    :alt="t('A recorded route drawn on the zone map')"
                    :caption="
                        t(
                            'Every session is drawn on the zone map: the line is coloured by how you travelled, and each event sits where it happened.',
                        )
                    "
                />
                <ProductShot
                    src="/screenshots/journey.png"
                    :alt="t('Play time, zones and levels over time')"
                    :caption="
                        t(
                            'The journey page adds up every session: time played, where it went, and when each level came.',
                        )
                    "
                />
            </div>

            <div class="mt-14 grid gap-4 md:grid-cols-3">
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="wow-panel flex flex-col gap-2 p-5"
                >
                    <span
                        class="font-mono text-[0.7rem] tracking-[0.25em] text-primary uppercase"
                    >
                        {{ t('Step :number', { number: index + 1 }) }}
                    </span>
                    <h2
                        class="font-display text-lg tracking-wide text-foreground"
                    >
                        {{ step.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">{{ step.text }}</p>
                </div>
            </div>
        </main>

        <footer
            class="mx-auto flex w-full max-w-5xl flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted-foreground"
        >
            <span class="font-mono tracking-wide uppercase">
                {{ t('Supported clients') }}
            </span>
            <span class="h-3 w-px bg-border" />
            <span v-for="client in clients" :key="client">{{ client }}</span>
        </footer>
    </div>
</template>
