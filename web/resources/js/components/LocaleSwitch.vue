<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import LocaleController from '@/actions/App/Http/Controllers/LocaleController';
import { locale } from '@/lib/i18n';

const page = usePage();

function select(value: string): void {
    if (value === locale) {
        return;
    }

    router.patch(
        LocaleController.update.url(),
        { locale: value },
        { onSuccess: () => window.location.reload() },
    );
}
</script>

<template>
    <div
        v-if="page.props.locales.length > 1"
        class="flex items-center gap-1 text-xs"
    >
        <template
            v-for="(option, index) in page.props.locales"
            :key="option.value"
        >
            <span v-if="index > 0" class="text-muted-foreground/50">/</span>
            <button
                type="button"
                :lang="option.value"
                :title="option.label"
                :class="[
                    'px-1 font-mono tracking-widest uppercase',
                    option.value === locale
                        ? 'text-gold'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
                @click="select(option.value)"
            >
                {{ option.value }}
            </button>
        </template>
    </div>
</template>
