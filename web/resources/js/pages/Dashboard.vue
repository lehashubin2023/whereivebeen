<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Compass, ScrollText, Upload, User } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const actions = [
    {
        icon: Upload,
        title: 'Import a session',
        text: 'Paste an exported log and add it to your chronicle.',
        href: '/game-session/import',
    },
    {
        icon: ScrollText,
        title: 'View imports',
        text: 'Track the status of your parsed journeys.',
        href: '/game-session/imports',
    },
    {
        icon: User,
        title: 'Your profile',
        text: 'Manage your traveler account and settings.',
        href: '/settings/profile',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1
                class="text-gold font-display text-2xl font-semibold tracking-wide"
            >
                Traveler's Log
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Chart your path across the world, one session at a time.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Link
                v-for="action in actions"
                :key="action.title"
                :href="action.href"
                class="wow-panel group flex flex-col gap-3 p-5 transition-transform hover:-translate-y-0.5"
            >
                <div
                    class="wow-frame flex size-10 items-center justify-center bg-sidebar"
                >
                    <component :is="action.icon" class="text-gold size-5" />
                </div>
                <h2
                    class="text-gold font-display text-base font-semibold tracking-wide"
                >
                    {{ action.title }}
                </h2>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ action.text }}
                </p>
            </Link>
        </div>

        <div class="wow-map-frame relative flex-1">
            <img
                src="/maps/Hellfire_Peninsula.png"
                alt="Map of Hellfire Peninsula, Outland"
                class="h-full object-cover"
            />
            <div
                class="pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 text-center"
            >
                <div
                    class="wow-panel pointer-events-auto flex flex-col items-center gap-2 px-8 py-6"
                >
                    <Compass class="text-gold size-7" />
                    <p class="text-gold font-display text-lg tracking-wide">
                        No route charted yet
                    </p>
                    <p class="max-w-xs text-sm text-muted-foreground">
                        Import a session to see your path drawn upon the map.
                    </p>
                    <Link
                        href="/game-session/import"
                        class="wow-btn mt-2 text-sm"
                    >
                        Import a session
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
