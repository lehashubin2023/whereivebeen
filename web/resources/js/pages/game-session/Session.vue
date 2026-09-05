<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Compass, MapPin } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RouteMap from '@/components/map/RouteMap.vue';

import type { SessionInfo, Zone } from '@/types';

const props = defineProps<{
    session: SessionInfo;
    zones: Zone[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sessions', href: '/game-session/sessions' },
            { title: 'Map', href: '#' },
        ],
    },
});

const title = computed(
    () =>
        props.session.character || `Session #${props.session.game_session_id}`,
);

const selectedZoneId = ref<number | null>(props.zones[0]?.id ?? null);

watch(
    () => props.zones,
    (zones) => {
        if (!zones.some((zone) => zone.id === selectedZoneId.value)) {
            selectedZoneId.value = zones[0]?.id ?? null;
        }
    },
);

const selectedZone = computed(
    () =>
        props.zones.find((zone) => zone.id === selectedZoneId.value) ??
        props.zones[0] ??
        null,
);

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1
                    class="text-gold font-display text-2xl font-semibold tracking-wide"
                >
                    {{ title }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    <span v-if="session.realm">{{ session.realm }} · </span>
                    <span v-if="selectedZone">{{ selectedZone.name }} · </span>
                    <span v-if="selectedZone"
                        >{{ selectedZone.points_count }} points ·
                    </span>
                    {{
                        formatDate(
                            selectedZone
                                ? selectedZone.time
                                : session.session_start_at,
                        )
                    }}
                </p>
            </div>
        </div>

        <div v-if="selectedZone" class="flex flex-1 flex-col gap-4 lg:flex-row">
            <aside class="shrink-0 lg:w-56">
                <div class="wow-panel flex flex-col gap-1 p-2">
                    <p
                        class="px-2 pt-1 pb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        Zones
                    </p>
                    <button
                        v-for="zone in zones"
                        :key="zone.id"
                        type="button"
                        class="flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors"
                        :class="
                            zone.id === selectedZoneId
                                ? 'text-gold bg-accent'
                                : 'text-muted-foreground hover:bg-muted/50'
                        "
                        @click="selectedZoneId = zone.id"
                    >
                        <MapPin class="size-4 shrink-0" />
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate">{{ zone.name }}</span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ formatDate(zone.time) }}
                            </span>
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ zone.points_count }}
                        </span>
                    </button>
                </div>
            </aside>

            <div class="flex-1">
                <RouteMap
                    :key="selectedZone.id"
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
                    Nothing to chart
                </p>
                <p class="max-w-xs text-sm text-muted-foreground">
                    This session has no mapped waypoints yet.
                </p>
            </div>
        </div>
    </div>
</template>
