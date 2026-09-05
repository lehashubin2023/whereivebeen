<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Compass, Footprints, Map, ScrollText } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login, register } from '@/routes';
import { sessions } from '@/routes/game-session';

const page = usePage();
const appName = page.props.name;

const features = [
    {
        icon: Footprints,
        title: 'Track every step',
        text: 'The in-game addon records your route, combat and events as you roam the world.',
    },
    {
        icon: ScrollText,
        title: 'Import your log',
        text: 'Paste the exported session and let it be parsed into your personal chronicle.',
    },
    {
        icon: Map,
        title: 'Relive the journey',
        text: 'Watch your path unfold across the authentic maps of Azeroth and Outland.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="relative flex min-h-svh flex-col px-6 py-6 lg:px-10">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between"
        >
            <Link :href="sessions()" class="flex items-center gap-3">
                <div
                    class="wow-frame flex size-11 items-center justify-center bg-sidebar"
                >
                    <AppLogoIcon class="text-gold size-6 fill-current" />
                </div>
                <span class="text-gold font-display text-lg tracking-[0.22em]">
                    {{ appName }}
                </span>
            </Link>

            <nav class="flex items-center gap-3">
                <Link
                    v-if="page.props.auth.user"
                    :href="sessions()"
                    class="wow-btn"
                >
                    Enter
                </Link>
                <template v-else>
                    <Link :href="login()" class="wow-btn-ghost">Log in</Link>
                    <Link :href="register()" class="wow-btn">Enlist</Link>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center py-14"
        >
            <div class="grid items-center gap-12 lg:grid-cols-2">
                <div class="flex flex-col">
                    <span
                        class="mb-4 font-display text-xs tracking-[0.4em] text-fel/90 uppercase"
                    >
                        Chronicle of the Journey
                    </span>
                    <h1
                        class="wow-title text-5xl leading-[1.05] font-black sm:text-6xl lg:text-7xl"
                    >
                        {{ appName }}
                    </h1>
                    <hr class="wow-divider-ornate my-7 max-w-md" />
                    <p
                        class="max-w-lg font-serif text-lg leading-relaxed text-foreground/90"
                    >
                        Every step you take across the world, remembered. Record
                        your travels in-game, import your log, and watch your
                        path be drawn across the maps of Azeroth and Outland.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-4">
                        <Link
                            v-if="page.props.auth.user"
                            :href="sessions()"
                            class="wow-btn text-sm"
                        >
                            <Compass class="size-4" />
                            Open your atlas
                        </Link>
                        <template v-else>
                            <Link :href="register()" class="wow-btn text-sm">
                                <Compass class="size-4" />
                                Begin your journey
                            </Link>
                            <Link :href="login()" class="wow-btn-ghost text-sm">
                                Return to the road
                            </Link>
                        </template>
                    </div>
                </div>

                <div class="relative">
                    <div class="wow-map-frame">
                        <img
                            src="/maps/Nagrand.png"
                            alt="Map of Nagrand, Outland"
                            loading="eager"
                        />
                    </div>
                    <div
                        class="wow-panel absolute -bottom-5 left-6 flex items-center gap-2 px-4 py-2"
                    >
                        <Compass class="text-gold size-4" />
                        <span
                            class="text-gold font-display text-xs tracking-[0.18em] uppercase"
                        >
                            Nagrand · Outland
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-24 grid gap-5 md:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="wow-panel flex flex-col gap-3 p-6"
                >
                    <div
                        class="wow-frame flex size-11 items-center justify-center bg-sidebar"
                    >
                        <component
                            :is="feature.icon"
                            class="text-gold size-5"
                        />
                    </div>
                    <h3
                        class="text-gold font-display text-lg font-semibold tracking-wide"
                    >
                        {{ feature.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>
