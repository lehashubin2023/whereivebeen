<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitch from '@/components/LocaleSwitch.vue';
import { locale, t } from '@/lib/i18n';
import { faq, home, login, register, support } from '@/routes';

type Props = {
    appName: string;
};

defineProps<Props>();
</script>

<template>
    <header
        class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-4"
    >
        <Link :href="home({ locale })" class="flex items-center gap-3">
            <div
                class="wow-frame flex size-11 items-center justify-center bg-sidebar"
            >
                <AppLogoIcon class="text-gold size-6 fill-current" />
            </div>
            <span class="text-gold font-display text-lg tracking-[0.22em]">
                {{ appName }}
            </span>
        </Link>

        <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            <LocaleSwitch />
            <Link :href="faq({ locale })" class="wow-btn-ghost">
                {{ t('FAQ') }}
            </Link>
            <Link :href="support({ locale })" class="wow-btn-ghost">
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
</template>
