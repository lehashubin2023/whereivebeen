<script setup lang="ts">
import { Skull } from '@lucide/vue';
import { computed } from 'vue';
import { locale, t } from '@/lib/i18n';
import type { Journey } from '@/types';

const props = defineProps<{ journey: Journey }>();

const hasActivity = computed(() =>
    props.journey.activity.some((day) => day.seconds > 0),
);

const peak = computed(() =>
    Math.max(1, ...props.journey.activity.map((day) => day.seconds)),
);

const bars = computed(() =>
    props.journey.activity.map((day) => ({
        ...day,
        share: day.seconds / peak.value,
        isPeak: day.seconds === peak.value && day.seconds > 0,
    })),
);

const zonePeak = computed(() =>
    Math.max(1, ...props.journey.zones.map((zone) => zone.seconds)),
);

function duration(seconds: number): string {
    if (seconds < 60) {
        return `${seconds}${t('s')}`;
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes}${t('m')}`;
    }

    return `${Math.floor(minutes / 60)}${t('h')} ${minutes % 60}${t('m')}`;
}

function shortDate(date: string): string {
    return new Date(date).toLocaleDateString(locale, {
        day: 'numeric',
        month: 'short',
    });
}
</script>

<template>
    <div class="grid gap-3 lg:grid-cols-3">
        <section class="wow-panel flex flex-col gap-3 p-4 lg:col-span-2">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-semibold tracking-wide text-foreground">
                    {{ t('Time in game') }}
                </h2>
                <span class="font-mono text-[11px] text-muted-foreground">
                    {{ t('per day') }}
                </span>
            </div>

            <div
                v-if="hasActivity"
                class="flex h-28 items-end gap-[2px] border-b"
                style="border-color: var(--border)"
                role="img"
                :aria-label="t('Time in game per day')"
            >
                <span
                    v-for="bar in bars"
                    :key="bar.date"
                    class="min-w-[2px] flex-1 rounded-t-[2px]"
                    style="background-color: var(--primary)"
                    :style="{
                        height: `${Math.max(bar.seconds > 0 ? 3 : 0, bar.share * 100)}%`,
                        opacity: bar.isPeak ? 1 : 0.55,
                    }"
                    :title="`${shortDate(bar.date)} — ${duration(bar.seconds)}`"
                />
            </div>

            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                {{ t('Not enough sessions to chart yet.') }}
            </p>

            <div
                v-if="hasActivity"
                class="flex items-baseline justify-between font-mono text-[11px] text-muted-foreground"
            >
                <span>{{ shortDate(journey.activity[0].date) }}</span>
                <span class="text-foreground">
                    {{
                        t('peak :duration', {
                            duration: duration(peak),
                        })
                    }}
                </span>
                <span>
                    {{
                        shortDate(
                            journey.activity[journey.activity.length - 1].date,
                        )
                    }}
                </span>
            </div>
        </section>

        <section class="wow-panel flex flex-col gap-3 p-4">
            <h2 class="font-semibold tracking-wide text-foreground">
                {{ t('Levels gained') }}
            </h2>

            <ol
                v-if="journey.levels.length"
                class="flex flex-col gap-2 overflow-y-auto"
            >
                <li
                    v-for="step in journey.levels"
                    :key="step.level"
                    class="flex items-baseline justify-between gap-3 text-sm"
                >
                    <span class="text-gold font-mono tabular-nums">
                        {{ step.level }}
                    </span>
                    <span
                        class="h-px flex-1 self-center"
                        style="background-color: var(--border)"
                    />
                    <span class="font-mono text-[11px] text-muted-foreground">
                        {{ shortDate(step.date) }}
                    </span>
                </li>
            </ol>

            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                {{ t('No level ups recorded yet.') }}
            </p>

            <template v-if="journey.deadliest">
                <hr class="wow-divider" />

                <div class="flex items-center gap-3">
                    <Skull class="size-5 shrink-0 text-destructive" />
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="font-mono text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            {{ t('Deadliest zone') }}
                        </span>
                        <span class="truncate text-sm text-foreground">
                            {{ journey.deadliest.name }}
                            <span class="text-muted-foreground">
                                ·
                                {{
                                    t(':count deaths', {
                                        count: journey.deadliest.deaths,
                                    })
                                }}
                            </span>
                        </span>
                    </div>
                </div>
            </template>
        </section>

        <section
            v-if="journey.zones.length"
            class="wow-panel flex flex-col gap-3 p-4 lg:col-span-3"
        >
            <h2 class="font-semibold tracking-wide text-foreground">
                {{ t('Where the time went') }}
            </h2>

            <ul class="flex flex-col gap-2">
                <li
                    v-for="zone in journey.zones"
                    :key="zone.name"
                    class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 text-sm"
                >
                    <span class="truncate text-foreground">{{
                        zone.name
                    }}</span>

                    <span
                        class="h-2 overflow-hidden rounded-sm"
                        style="background-color: var(--muted)"
                    >
                        <span
                            class="block h-full rounded-sm"
                            style="background-color: var(--primary)"
                            :style="{
                                width: `${Math.max(2, (zone.seconds / zonePeak) * 100)}%`,
                            }"
                        />
                    </span>

                    <span
                        class="font-mono text-[11px] text-muted-foreground tabular-nums"
                    >
                        {{ duration(zone.seconds) }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
