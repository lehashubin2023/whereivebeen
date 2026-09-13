<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChartColumn } from '@lucide/vue';
import { computed, ref } from 'vue';

import JourneyOverview from '@/components/JourneyOverview.vue';
import { t } from '@/lib/i18n';
import type { Journey, StatisticGroup, StatisticOverview } from '@/types';

const props = defineProps<{
    overview: StatisticOverview[];
    groups: StatisticGroup[];
    journey: Journey;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Journey'), href: '/statistics' }],
    },
});

const selectedSlug = ref<string | null>(props.groups[0]?.slug ?? null);

const selectedGroup = computed(
    () =>
        props.groups.find((group) => group.slug === selectedSlug.value) ??
        props.groups[0] ??
        null,
);
</script>

<template>
    <Head :title="t('Journey')" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <h1 class="sr-only">{{ t('Journey') }}</h1>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="item in overview"
                :key="item.label"
                class="wow-panel flex flex-col gap-1 px-4 py-3"
            >
                <span
                    class="font-mono text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    {{ item.label }}
                </span>
                <span class="font-mono text-xl text-foreground tabular-nums">
                    {{ item.value }}
                </span>
            </div>
        </div>

        <JourneyOverview :journey="journey" />

        <div
            v-if="selectedGroup"
            class="flex flex-1 flex-col gap-4 lg:flex-row"
        >
            <aside class="shrink-0 lg:w-64">
                <div class="wow-panel flex flex-col gap-1 p-2">
                    <button
                        v-for="group in groups"
                        :key="group.slug"
                        type="button"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors"
                        :class="
                            group.slug === selectedGroup.slug
                                ? 'text-gold bg-accent'
                                : 'text-muted-foreground hover:bg-muted/50'
                        "
                        @click="selectedSlug = group.slug"
                    >
                        <span class="min-w-0 flex-1 truncate">
                            {{ group.label }}
                        </span>
                        <span class="shrink-0 text-xs tabular-nums opacity-70">
                            {{ group.total }}
                        </span>
                    </button>
                </div>
            </aside>

            <div class="flex flex-1 flex-col gap-4">
                <p class="text-sm text-muted-foreground">
                    <span class="font-semibold tracking-wide text-foreground">
                        {{ selectedGroup.label }}
                    </span>
                    ·
                    {{ t(':count events', { count: selectedGroup.total }) }}
                </p>

                <div
                    v-if="selectedGroup.tables.length"
                    class="grid gap-4 xl:grid-cols-2"
                >
                    <div
                        v-for="table in selectedGroup.tables"
                        :key="table.title"
                        class="wow-panel flex flex-col"
                    >
                        <div
                            class="flex items-baseline justify-between gap-2 border-b border-border/70 px-4 py-3"
                        >
                            <h2
                                class="font-semibold tracking-wide text-foreground"
                            >
                                {{ table.title }}
                            </h2>
                            <span
                                v-if="table.rows_total > table.rows.length"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    t('top :shown of :total', {
                                        shown: table.rows.length,
                                        total: table.rows_total,
                                    })
                                }}
                            </span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead
                                    class="border-b border-border/70 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th
                                            v-for="(
                                                column, index
                                            ) in table.columns"
                                            :key="column"
                                            class="px-4 py-2 font-medium"
                                            :class="
                                                index ? 'text-right' : 'w-full'
                                            "
                                        >
                                            {{ column }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, rowIndex) in table.rows"
                                        :key="rowIndex"
                                        class="border-b border-border/40 last:border-0 hover:bg-muted/40"
                                    >
                                        <td
                                            v-for="(cell, index) in row"
                                            :key="index"
                                            class="px-4 py-2"
                                            :class="
                                                index
                                                    ? 'text-right text-muted-foreground tabular-nums'
                                                    : 'text-foreground'
                                            "
                                        >
                                            {{ cell }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="wow-panel flex flex-1 items-center justify-center p-12 text-center text-sm text-muted-foreground"
                >
                    {{ t('This event has no extra details to break down.') }}
                </div>
            </div>
        </div>

        <div
            v-else
            class="wow-map-frame relative flex min-h-[280px] flex-1 items-center justify-center p-6"
        >
            <div
                class="wow-panel flex flex-col items-center gap-2 px-8 py-6 text-center"
            >
                <ChartColumn class="text-gold size-7" />
                <p class="font-display text-lg tracking-wide text-foreground">
                    {{ t('Nothing to count') }}
                </p>
                <p class="max-w-xs text-sm text-muted-foreground">
                    {{
                        t('Import a session and your deeds will show up here.')
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
