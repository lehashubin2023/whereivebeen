<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

interface SessionRow {
    id: number;
    game_session_id: number;
    character: string;
    realm: string;
    points_count: number;
    session_start_at: string | null;
}

interface SessionsPaginator {
    data: SessionRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ sessions: SessionsPaginator }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sessions', href: '/game-session/sessions' }],
    },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Sessions" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1
                class="text-gold font-display text-2xl font-semibold tracking-wide"
            >
                Sessions
            </h1>
            <Link
                href="/game-session/import"
                class="text-gold text-sm underline-offset-4 hover:underline"
            >
                New import
            </Link>
        </div>

        <div v-if="sessions.data.length" class="wow-panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Character</th>
                        <th class="px-4 py-3 font-medium">Realm</th>
                        <th class="px-4 py-3 font-medium">Session</th>
                        <th class="px-4 py-3 font-medium">Points</th>
                        <th class="px-4 py-3 font-medium">Started</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in sessions.data"
                        :key="row.id"
                        class="group border-b border-border/40 last:border-0 hover:bg-muted/40"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/game-session/sessions/${row.id}`"
                                class="text-gold font-medium underline-offset-4 group-hover:underline"
                            >
                                {{ row.character || '—' }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.realm || '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.game_session_id }}
                        </td>
                        <td class="px-4 py-3">{{ row.points_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(row.session_start_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="`/game-session/sessions/${row.id}`"
                                class="text-muted-foreground group-hover:text-gold"
                                aria-label="Open map"
                            >
                                <ChevronRight class="ml-auto size-4" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="wow-panel flex flex-col items-center justify-center gap-3 p-12 text-center"
        >
            <p class="text-muted-foreground">No sessions yet.</p>
            <Link
                href="/game-session/import"
                class="text-gold underline-offset-4 hover:underline"
            >
                Import your first session
            </Link>
        </div>

        <div
            v-if="sessions.prev_page_url || sessions.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="sessions.prev_page_url"
                :href="sessions.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Previous
            </Link>
            <span v-else />
            <Link
                v-if="sessions.next_page_url"
                :href="sessions.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Next
            </Link>
        </div>
    </div>
</template>
