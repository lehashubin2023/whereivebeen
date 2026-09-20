<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitch from '@/components/LocaleSwitch.vue';
import { t } from '@/lib/i18n';
import { faq, home, login, register, support } from '@/routes';

type Props = {
    appName: string;
};

defineProps<Props>();

const page = usePage();
</script>

<template>
    <header
        class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-4"
    >
        <Link :href="home()" class="flex items-center gap-3">
            <AppLogoIcon class="text-gold size-9" />
            <span
                class="font-mono text-sm font-medium tracking-[0.2em] text-foreground uppercase"
            >
                {{ appName }}
            </span>
        </Link>

        <nav class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
            <LocaleSwitch />
            <Link :href="faq()" class="wow-btn-ghost">
                {{ t('FAQ') }}
            </Link>
            <Link
                v-if="page.props.supportAvailable"
                :href="support()"
                class="wow-btn-ghost"
            >
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
