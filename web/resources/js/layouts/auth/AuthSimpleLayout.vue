<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { locale } from '@/lib/i18n';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const name = page.props.name;

watchEffect(() => {
    const map = page.props.backgroundMap;

    if (map && typeof document !== 'undefined') {
        document.documentElement.style.setProperty(
            '--page-bg-image',
            `url('${map}')`,
        );
    }
});
</script>

<template>
    <div
        class="page-bg flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center gap-6">
                <Link
                    :href="home({ locale })"
                    class="flex flex-col items-center gap-3 font-medium"
                >
                    <div
                        class="wow-frame flex h-14 w-14 items-center justify-center bg-sidebar"
                    >
                        <AppLogoIcon class="text-gold size-8 fill-current" />
                    </div>
                    <span
                        class="text-gold font-display text-lg tracking-[0.2em]"
                    >
                        {{ name }}
                    </span>
                </Link>

                <div class="wow-panel w-full p-6">
                    <div class="mb-4 space-y-2 text-center">
                        <h1
                            class="text-gold font-display text-xl font-semibold tracking-wide"
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
