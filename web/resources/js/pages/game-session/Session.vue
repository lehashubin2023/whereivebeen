<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Compass, MapPin } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RouteMap from '@/components/map/RouteMap.vue';
import { locale, t } from '@/lib/i18n';

import type { SessionInfo, Zone } from '@/types';

const props = defineProps<{
    session: SessionInfo;
    zones: Zone[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('Sessions'), href: '/game-session/sessions' },
            { title: t('Map'), href: '#' },
        ],
    },
});

const title = computed(
    () =>
        props.session.character ||
        t('Session #:id', { id: props.session.game_session_id }),
);

const selectedZoneKey = ref<string | null>(props.zones[0]?.key ?? null);

watch(
    () => props.zones,
    (zones) => {
        if (!zones.some((zone) => zone.key === selectedZoneKey.value)) {
            selectedZoneKey.value = zones[0]?.key ?? null;
        }
    },
);

const selectedZone = computed(
    () =>
        props.zones.find((zone) => zone.key === selectedZoneKey.value) ??
        props.zones[0] ??
        null,
);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString(locale) : '—';
}

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDuration(seconds: number): string {
    if (seconds < 60) {
        return `${seconds}${t('s')}`;
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes}${t('m')}`;
    }

    return `${Math.floor(minutes / 60)}${t('h')} ${minutes % 60}${t('m')}`;
}

const visits = computed(() =>
    props.zones.map((zone, index) => {
        const day = new Date(zone.time).toDateString();
        const previous = props.zones[index - 1];

        return {
            zone,
            day:
                previous && new Date(previous.time).toDateString() === day
                    ? null
                    : new Date(zone.time).toLocaleDateString(locale, {
                          month: 'short',
                          day: 'numeric',
                      }),
        };
    }),
);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <h1 class="sr-only">{{ title }}</h1>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                <span class="text-gold font-display tracking-wide">
                    {{ title }}
                </span>
                <span v-if="session.realm"> · {{ session.realm }}</span>
                <span v-if="selectedZone"> · {{ selectedZone.name }}</span>
                <span v-if="selectedZone">
                    ·
                    {{
                        t(':count points', {
                            count: selectedZone.points_count,
                        })
                    }}</span
                >
                <span v-if="selectedZone">
                    · {{ formatDuration(selectedZone.duration) }}</span
                >
                ·
                {{
                    formatDate(
                        selectedZone
                            ? selectedZone.time
                            : session.session_start_at,
                    )
                }}
            </p>
        </div>

        <div v-if="selectedZone" class="flex flex-1 flex-col gap-4 lg:flex-row">
            <aside class="shrink-0 lg:w-64">
                <div class="wow-panel flex flex-col gap-1 p-2">
                    <template v-for="visit in visits" :key="visit.zone.key">
                        <p
                            v-if="visit.day"
                            class="text-gold/70 px-2 pt-2 pb-1 text-[11px] tracking-wide uppercase"
                        >
                            {{ visit.day }}
                        </p>

                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors"
                            :class="
                                visit.zone.key === selectedZoneKey
                                    ? 'text-gold bg-accent'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                            @click="selectedZoneKey = visit.zone.key"
                        >
                            <MapPin class="size-4 shrink-0" />
                            <span class="min-w-0 flex-1 truncate">
                                {{ visit.zone.name }}
                            </span>
                            <span
                                class="shrink-0 text-xs tabular-nums opacity-70"
                            >
                                {{ formatTime(visit.zone.time) }}
                            </span>
                        </button>
                    </template>
                </div>
            </aside>

            <div class="flex-1">
                <RouteMap
                    :key="selectedZone.key"
                    :image="selectedZone.image_path"
                    :points="selectedZone.points"
                    :game-session-id="session.id"
                />
            </div>
        </div>

        <div
            v-else
            class="wow-map-frame relative flex min-h-[280px] flex-1 items-center justify-center p-6"
        >
            <div
                class="wow-panel flex flex-col items-center gap-2 px-8 py-6 text-center"
            >
                <Compass class="text-gold size-7" />
                <p class="text-gold font-display text-lg tracking-wide">
                    {{ t('Nothing to chart') }}
                </p>
                <p class="max-w-xs text-sm text-muted-foreground">
                    {{ t('This session has no mapped waypoints yet.') }}
                </p>
            </div>
        </div>
    </div>
</template>
