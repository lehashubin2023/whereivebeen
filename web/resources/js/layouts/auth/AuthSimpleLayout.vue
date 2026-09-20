<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBackgroundMap } from '@/composables/useBackgroundMap';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const name = page.props.name;

useBackgroundMap();
</script>

<template>
    <div
        class="page-bg flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-6">
                <Link
                    :href="home()"
                    class="flex flex-col items-center gap-3 font-medium"
                >
                    <div class="flex h-14 w-14 items-center justify-center">
                        <AppLogoIcon class="text-gold size-12" />
                    </div>
                    <span
                        class="font-mono text-sm font-medium tracking-[0.2em] text-foreground uppercase"
                    >
                        {{ name }}
                    </span>
                </Link>

                <div class="wow-panel w-full p-6">
                    <div class="mb-4 space-y-2 text-center">
                        <h1
                            class="font-display text-xl tracking-wide text-foreground"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                    <hr class="wow-divider mb-6" />
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
