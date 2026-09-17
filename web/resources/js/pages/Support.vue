<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Check, Copy, Heart, Send } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, onUnmounted, ref } from 'vue';
import PublicHeader from '@/components/PublicHeader.vue';
import { t } from '@/lib/i18n';
import type { SupportChannels } from '@/types';

type Props = {
    channels: SupportChannels;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Support the project'), href: '/support' }],
    },
});

const page = usePage();
const appName = page.props.name;
const meta = computed(() => page.props.meta);
const isGuest = computed(() => !page.props.auth?.user);

const hasAnyChannel = computed(
    () =>
        Boolean(props.channels.boosty) ||
        Boolean(props.channels.telegram) ||
        props.channels.crypto.length > 0,
);

const { copy } = useClipboard();
const copiedAddress = ref<string | null>(null);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyAddress(address: string): Promise<void> {
    await copy(address);

    copiedAddress.value = address;

    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => {
        copiedAddress.value = null;
    }, 2000);
}

onUnmounted(() => clearTimeout(resetTimer));
</script>

<template>
    <Head :title="meta.title" />

    <div
        :class="
            isGuest
                ? 'flex min-h-svh flex-col bg-background px-6 py-6 lg:px-10'
                : 'flex flex-1 flex-col p-4 md:p-8'
        "
    >
        <PublicHeader v-if="isGuest" :app-name="appName" class="mb-10" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <div class="wow-panel flex flex-col gap-3 p-6">
                <h1
                    class="flex items-center gap-2 font-display text-lg tracking-wide text-foreground"
                >
                    <Heart class="size-5" />
                    {{ t('Support the project') }}
                </h1>
                <hr class="wow-divider" />
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'WhereIveBeen is free and has no paid features. If it is useful to you, you can help cover the servers — this is entirely voluntary and unlocks nothing.',
                        )
                    }}
                </p>
            </div>

            <div
                v-if="channels.boosty"
                class="wow-panel flex flex-col gap-3 p-6"
            >
                <h2 class="font-display text-lg tracking-wide text-foreground">
                    Boosty
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'One-off or monthly support with a bank card. The most convenient option inside Russia.',
                        )
                    }}
                </p>
                <div>
                    <a
                        :href="channels.boosty"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="wow-btn text-sm"
                        data-test="boosty-link"
                    >
                        <Heart class="size-4" />
                        {{ t('Open Boosty') }}
                    </a>
                </div>
            </div>

            <div
                v-if="channels.telegram"
                class="wow-panel flex flex-col gap-3 p-6"
            >
                <h2 class="font-display text-lg tracking-wide text-foreground">
                    {{ t('Telegram Stars') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Pay from inside Telegram with Stars — no card and no registration needed.',
                        )
                    }}
                </p>
                <div>
                    <a
                        :href="channels.telegram"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="wow-btn-ghost text-sm"
                        data-test="telegram-link"
                    >
                        <Send class="size-4" />
                        {{ t('Open in Telegram') }}
                    </a>
                </div>
            </div>

            <div
                v-if="channels.crypto.length"
                class="wow-panel flex flex-col gap-3 p-6"
            >
                <h2 class="font-display text-lg tracking-wide text-foreground">
                    {{ t('Crypto') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{
                        t(
                            'Works from anywhere without intermediaries. Transfers cannot be reversed, so check the network before sending.',
                        )
                    }}
                </p>

                <ul class="flex flex-col gap-3">
                    <li
                        v-for="wallet in channels.crypto"
                        :key="wallet.label"
                        class="flex flex-col gap-2 border-t border-border/40 pt-3 first:border-0 first:pt-0"
                    >
                        <span class="text-xs tracking-wide text-foreground/90">
                            {{ wallet.label }}
                        </span>
                        <div class="flex items-center gap-2">
                            <code
                                class="min-w-0 flex-1 font-mono text-xs break-all text-muted-foreground"
                            >
                                {{ wallet.address }}
                            </code>
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1 rounded-md border border-border/60 px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                                :data-test="`copy-${wallet.label}`"
                                @click="copyAddress(wallet.address)"
                            >
                                <Check
                                    v-if="copiedAddress === wallet.address"
                                    class="size-3.5"
                                />
                                <Copy v-else class="size-3.5" />
                                {{
                                    copiedAddress === wallet.address
                                        ? t('Copied')
                                        : t('Copy')
                                }}
                            </button>
                        </div>
                    </li>
                </ul>
            </div>

            <div
                v-if="!hasAnyChannel"
                class="wow-panel flex flex-col items-center justify-center gap-3 p-12 text-center"
            >
                <p class="text-muted-foreground">
                    {{ t('No support channels are set up yet.') }}
                </p>
            </div>
        </div>
    </div>
</template>
