<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, Copy, Hourglass } from '@lucide/vue';
import { ref } from 'vue';
import { t } from '@/lib/i18n';

const PATHS = [
    {
        os: 'Windows',
        path: 'C:\\Program Files (x86)\\World of Warcraft\\_classic_era_\\Interface\\AddOns\\WhereIveBeen',
    },
    {
        os: 'macOS',
        path: '/Applications/World of Warcraft/_classic_era_/Interface/AddOns/WhereIveBeen',
    },
];

const copied = ref<string | null>(null);

async function copy(path: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(path);
        copied.value = path;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        copied.value = null;
    }
}
</script>

<template>
    <div class="wow-panel flex flex-col gap-5 p-6">
        <div class="flex flex-col gap-1">
            <h2 class="font-display text-lg tracking-wide text-foreground">
                {{ t('Three steps to your first route') }}
            </h2>
            <p class="text-sm text-muted-foreground">
                {{
                    t(
                        'Nothing is recorded yet — the addon has to run in game first.',
                    )
                }}
            </p>
        </div>

        <ol class="flex flex-col gap-4">
            <li class="flex gap-3">
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full border border-border font-mono text-[11px] text-muted-foreground"
                >
                    1
                </span>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-foreground">
                        {{ t('Download the addon and unpack the archive.') }}
                    </span>
                    <Link
                        href="/addon"
                        class="text-gold self-start text-xs underline-offset-4 hover:underline"
                    >
                        {{ t('Go to the addon page') }}
                    </Link>
                </div>
            </li>

            <li class="flex gap-3">
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full border border-border font-mono text-[11px] text-muted-foreground"
                >
                    2
                </span>
                <div class="flex min-w-0 flex-col gap-2 text-sm">
                    <span class="text-foreground">
                        {{
                            t(
                                'Put it in Interface/AddOns — the folder must be named WhereIveBeen.',
                            )
                        }}
                    </span>

                    <div class="flex flex-col gap-1">
                        <button
                            v-for="entry in PATHS"
                            :key="entry.os"
                            type="button"
                            class="group flex items-center gap-2 rounded border border-border/60 px-2 py-1 text-left"
                            :title="t('Copy path')"
                            @click="copy(entry.path)"
                        >
                            <span
                                class="w-14 shrink-0 font-mono text-[10px] tracking-wide text-muted-foreground uppercase"
                            >
                                {{ entry.os }}
                            </span>
                            <code
                                class="min-w-0 flex-1 truncate border-0 bg-transparent p-0 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ entry.path }}
                            </code>
                            <Check
                                v-if="copied === entry.path"
                                class="size-3 shrink-0 text-fel"
                            />
                            <Copy
                                v-else
                                class="size-3 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                            />
                        </button>
                    </div>
                </div>
            </li>

            <li class="flex gap-3">
                <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full border border-border"
                >
                    <Hourglass class="size-3 text-muted-foreground" />
                </span>
                <div class="flex flex-col gap-1 text-sm">
                    <span class="text-foreground">
                        {{ t('Play, log out, then import the session.') }}
                    </span>
                    <Link
                        href="/game-session/imports"
                        class="text-gold self-start text-xs underline-offset-4 hover:underline"
                    >
                        {{ t('Go to imports') }}
                    </Link>
                </div>
            </li>
        </ol>
    </div>
</template>
