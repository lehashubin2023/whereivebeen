<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';

type ImportStatus = 'new' | 'in_process' | 'completed' | 'failed';

interface ImportRow {
    id: number;
    status: ImportStatus;
    points_total: number;
    points_done: number;
    execution_time: number;
    error_message: string | null;
    game_session_id: number | null;
    created_at: string | null;
}

interface ImportsPaginator {
    data: ImportRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ imports: ImportsPaginator }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imports', href: '/game-session/imports' }],
    },
});

const statusClass: Record<ImportStatus, string> = {
    new: 'bg-muted text-muted-foreground border-border',
    in_process: 'bg-accent text-accent-foreground border-accent',
    completed: 'bg-emerald-900/40 text-emerald-300 border-emerald-700/50',
    failed: 'bg-destructive/20 text-destructive border-destructive/40',
};

const statusLabel: Record<ImportStatus, string> = {
    new: 'New',
    in_process: 'In process',
    completed: 'Completed',
    failed: 'Failed',
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Imports" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1
                class="text-gold font-display text-2xl font-semibold tracking-wide"
            >
                Imports
            </h1>
            <Link
                href="/game-session/import"
                class="text-gold text-sm underline-offset-4 hover:underline"
            >
                New import
            </Link>
        </div>

        <div v-if="imports.data.length" class="wow-panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Session</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Points</th>
                        <th class="px-4 py-3 font-medium">Time</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in imports.data"
                        :key="row.id"
                        class="border-b border-border/40 last:border-0"
                    >
                        <td class="px-4 py-3">
                            {{ row.game_session_id ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="statusClass[row.status]"
                            >
                                {{ statusLabel[row.status] }}
                            </Badge>
                            <p
                                v-if="row.error_message"
                                :title="row.error_message"
                                class="mt-1 max-w-xs truncate text-xs text-destructive"
                            >
                                {{ row.error_message }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            {{ row.points_done }} / {{ row.points_total }}
                        </td>
                        <td class="px-4 py-3">{{ row.execution_time }}s</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(row.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="wow-panel flex flex-col items-center justify-center gap-3 p-12 text-center"
        >
            <p class="text-muted-foreground">No imports yet.</p>
            <Link
                href="/game-session/import"
                class="text-gold underline-offset-4 hover:underline"
            >
                Import your first session
            </Link>
        </div>

        <div
            v-if="imports.prev_page_url || imports.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="imports.prev_page_url"
                :href="imports.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Previous
            </Link>
            <span v-else />
            <Link
                v-if="imports.next_page_url"
                :href="imports.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Next
            </Link>
        </div>
    </div>
</template>
