<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import SetupChecklist from '@/components/SetupChecklist.vue';
import { locale, t } from '@/lib/i18n';

interface SessionRow {
    id: number;
    game_session_id: number;
    character: string;
    realm: string;
    class: string | null;
    points_count: number;
    session_start_at: string;
    duration: number;
    zones: string[];
    level_from: number | null;
    level_to: number | null;
}

interface CharacterOption {
    character: string;
    realm: string;
    sessions: number;
}

interface SessionsPaginator {
    data: SessionRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    sessions: SessionsPaginator;
    characters: CharacterOption[];
    filters: { character: string | null; realm: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Sessions'), href: '/game-session/sessions' }],
    },
});

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

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function title(row: SessionRow): string {
    if (row.zones.length > 1) {
        return `${row.zones[0]} → ${row.zones[row.zones.length - 1]}`;
    }

    if (row.zones.length === 1) {
        return row.zones[0];
    }

    return t('Session #:id', { id: row.game_session_id });
}

function levels(row: SessionRow): string | null {
    if (row.level_from === null && row.level_to === null) {
        return null;
    }

    if (row.level_from === row.level_to || row.level_to === null) {
        return t('Level :level', { level: String(row.level_from) });
    }

    return `${row.level_from} → ${row.level_to}`;
}

/** Заголовок-дата ставится там, где начинается новый день. */
const grouped = computed(() =>
    props.sessions.data.map((row, index) => {
        const day = new Date(row.session_start_at).toDateString();
        const previous = props.sessions.data[index - 1];

        return {
            row,
            day:
                previous &&
                new Date(previous.session_start_at).toDateString() === day
                    ? null
                    : new Date(row.session_start_at).toLocaleDateString(
                          locale,
                          { day: 'numeric', month: 'long', year: 'numeric' },
                      ),
        };
    }),
);

const selectedCharacter = computed(() =>
    props.filters.character && props.filters.realm
        ? `${props.filters.character}|${props.filters.realm}`
        : '',
);

function filterByCharacter(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;
    const [character, realm] = value.split('|');

    router.get('/game-session/sessions', value ? { character, realm } : {}, {
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head :title="t('Sessions')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Sessions') }}</h1>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <select
                v-if="characters.length > 1"
                :value="selectedCharacter"
                class="rounded-md border border-border bg-card px-3 py-2 text-sm text-foreground"
                :aria-label="t('Character')"
                @change="filterByCharacter"
            >
                <option value="">{{ t('All characters') }}</option>
                <option
                    v-for="option in characters"
                    :key="`${option.character}|${option.realm}`"
                    :value="`${option.character}|${option.realm}`"
                >
                    {{ option.character }} — {{ option.realm }} ({{
                        option.sessions
                    }})
                </option>
            </select>
            <span v-else />

            <Link
                href="/game-session/imports"
                class="text-gold text-sm underline-offset-4 hover:underline"
            >
                {{ t('New import') }}
            </Link>
        </div>

        <div v-if="sessions.data.length" class="flex flex-col gap-2">
            <template v-for="item in grouped" :key="item.row.id">
                <p
                    v-if="item.day"
                    class="px-1 pt-4 pb-1 font-mono text-[11px] tracking-wide text-muted-foreground uppercase first:pt-0"
                >
                    {{ item.day }}
                </p>

                <Link
                    :href="`/game-session/sessions/${item.row.id}`"
                    class="wow-panel group flex items-center gap-4 px-4 py-3 transition-colors hover:border-primary/50"
                >
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span
                            class="group-hover:text-gold truncate font-medium text-foreground"
                        >
                            {{ title(item.row) }}
                        </span>

                        <span
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 font-mono text-[11px] text-muted-foreground"
                        >
                            <span>{{
                                formatTime(item.row.session_start_at)
                            }}</span>
                            <span>·</span>
                            <span>{{ formatDuration(item.row.duration) }}</span>
                            <span>·</span>
                            <span>
                                {{
                                    t(':count points', {
                                        count: item.row.points_count,
                                    })
                                }}
                            </span>
                            <template v-if="levels(item.row)">
                                <span>·</span>
                                <span class="text-gold">{{
                                    levels(item.row)
                                }}</span>
                            </template>
                            <template v-if="characters.length > 1">
                                <span>·</span>
                                <span>{{ item.row.character }}</span>
                            </template>
                        </span>
                    </div>

                    <ChevronRight
                        class="group-hover:text-gold size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
            </template>
        </div>

        <SetupChecklist v-else />

        <div
            v-if="sessions.prev_page_url || sessions.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="sessions.prev_page_url"
                :href="sessions.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                {{ t('Previous') }}
            </Link>
            <span v-else />
            <Link
                v-if="sessions.next_page_url"
                :href="sessions.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                {{ t('Next') }}
            </Link>
        </div>
    </div>
</template>
