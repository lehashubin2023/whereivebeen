<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, FileUp, Inbox } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ImportController from '@/actions/App/Http/Controllers/GameSession/ImportController';
import EmptyState from '@/components/EmptyState.vue';
import FileDropZone from '@/components/import/FileDropZone.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/datetime';
import { t } from '@/lib/i18n';
import { importErrorHint } from '@/lib/importErrors';
import type {
    ImportBatchesPaginator,
    ImportBatchState,
    ImportRow,
    ImportsPaginator,
    ImportStatus,
    ImportTab,
} from '@/types';

const props = defineProps<{
    imports: ImportsPaginator;
    batches: ImportBatchesPaginator;
    tab: ImportTab;
    fileLimitMb: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Imports'), href: '/game-session/imports' }],
    },
});

const expanded = ref<number | null>(null);
const sessionInput = ref('');
const dropZone = ref<InstanceType<typeof FileDropZone> | null>(null);

const tabs: { key: ImportTab; label: string }[] = [
    { key: 'sessions', label: t('Sessions') },
    { key: 'files', label: t('Files') },
];

function tabHref(key: ImportTab): string {
    return ImportController.index.url({ query: { tab: key } });
}

const statusClass: Record<ImportStatus, string> = {
    new: 'text-muted-foreground',
    in_process: 'text-gold',
    completed: 'text-emerald-400',
    failed: 'text-destructive',
};

const statusLabel: Record<ImportStatus, string> = {
    new: t('New'),
    in_process: t('In process'),
    completed: t('Completed'),
    failed: t('Failed'),
};

const batchStateLabel: Record<ImportBatchState, string> = {
    queued: t('Queued'),
    parsing: t('Reading file'),
    importing: t('Importing sessions'),
    completed: t('Import finished'),
    failed: t('Failed'),
};

const batchStateClass: Record<ImportBatchState, string> = {
    queued: 'text-muted-foreground',
    parsing: 'text-gold',
    importing: 'text-gold',
    completed: 'text-emerald-400',
    failed: 'text-destructive',
};

const skipReasonLabel: Record<string, string> = {
    no_points: t('No recorded points'),
    no_character: t('No character name'),
    no_session_id: t('No session id'),
    no_start_time: t('No start time'),
    too_large: t('Too large to import'),
};

function unknownMaps(row: ImportRow): number {
    return Object.keys(row.warnings?.unknown_maps ?? {}).length;
}

function newMaps(row: ImportRow): number {
    return (row.warnings?.new_maps ?? []).length;
}

function toggle(id: number): void {
    expanded.value = expanded.value === id ? null : id;
}

const POLL_MS = 3000;

const working = computed(
    () =>
        props.batches.data.some((batch) => !batch.settled) ||
        props.imports.data.some(
            (row) => row.status === 'new' || row.status === 'in_process',
        ),
);

const { pause, resume } = useIntervalFn(
    () => router.reload({ only: ['imports', 'batches'] }),
    POLL_MS,
    { immediate: false },
);

watch(working, (isWorking) => (isWorking ? resume() : pause()), {
    immediate: true,
});
</script>

<template>
    <Head :title="t('Imports')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Imports') }}</h1>

        <nav
            class="mb-6 flex gap-1 self-start rounded-md border border-border/60 p-1"
        >
            <Link
                v-for="item in tabs"
                :key="item.key"
                :href="tabHref(item.key)"
                preserve-scroll
                class="rounded px-4 py-1.5 text-sm transition-colors"
                :class="
                    tab === item.key
                        ? 'bg-muted font-medium text-foreground'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :data-test="`imports-tab-${item.key}`"
            >
                {{ item.label }}
            </Link>
        </nav>

        <template v-if="tab === 'sessions'">
            <div class="wow-panel mb-6 flex flex-col p-6">
                <h2 class="font-display text-lg tracking-wide text-foreground">
                    {{ t('Paste a session') }}
                </h2>
                <hr class="wow-divider my-4" />
                <p class="mb-6 text-sm text-muted-foreground">
                    {{
                        t(
                            'Paste the session exported from the addon and submit it for processing.',
                        )
                    }}
                </p>

                <Form
                    v-bind="ImportController.store.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-col gap-4"
                    @success="sessionInput = ''"
                >
                    <div class="grid flex-1 gap-2">
                        <Textarea
                            id="game_session"
                            v-model="sessionInput"
                            name="game_session"
                            rows="8"
                            :aria-invalid="Boolean(errors.game_session)"
                            :placeholder="
                                t('Paste the exported session, e.g. WIVB1:…')
                            "
                            class="min-h-40 font-mono"
                        />
                        <InputError :message="errors.game_session" />
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing"
                        data-test="import-session-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('Import') }}
                    </Button>
                </Form>
            </div>

            <div v-if="imports.data.length" class="wow-panel overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-border/70 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Session') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Status') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Points') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Time') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Created') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in imports.data"
                            :key="row.id"
                            class="border-b border-border/40 align-top last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    v-if="row.game_session_id"
                                    :href="`/game-session/sessions/${row.game_session_id}`"
                                    class="text-gold underline-offset-4 hover:underline"
                                >
                                    {{ row.game_session_id }}
                                </Link>
                                <span v-else>—</span>

                                <p
                                    v-if="row.outcome === 'replaced'"
                                    class="mt-1 font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ t('Replaced') }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="status"
                                    :class="statusClass[row.status]"
                                >
                                    {{ statusLabel[row.status] }}
                                </Badge>

                                <div
                                    v-if="row.error_code"
                                    class="mt-2 max-w-sm text-xs"
                                >
                                    <p class="text-destructive">
                                        {{
                                            importErrorHint(
                                                row.error_code,
                                                row.error_context,
                                            )?.title
                                        }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{
                                            importErrorHint(
                                                row.error_code,
                                                row.error_context,
                                            )?.hint
                                        }}
                                    </p>

                                    <button
                                        v-if="row.error_message"
                                        type="button"
                                        class="mt-1 inline-flex items-center gap-1 text-muted-foreground underline-offset-4 hover:underline"
                                        @click="toggle(row.id)"
                                    >
                                        <ChevronDown
                                            class="size-3 transition-transform"
                                            :class="
                                                expanded === row.id
                                                    ? 'rotate-180'
                                                    : ''
                                            "
                                        />
                                        {{ t('Technical details') }}
                                    </button>

                                    <pre
                                        v-if="expanded === row.id"
                                        class="mt-1 overflow-x-auto rounded border border-border/60 bg-muted/40 p-2 font-mono text-[11px] whitespace-pre-wrap text-muted-foreground"
                                        >{{ row.error_message }}</pre>
                                </div>

                                <p
                                    v-if="newMaps(row)"
                                    class="mt-2 max-w-sm text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            ':count new zones were added to the site — map images for them are not available yet.',
                                            { count: newMaps(row) },
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="unknownMaps(row)"
                                    class="mt-2 max-w-sm text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            ':count zones are not on the site yet — the route was saved, but those maps cannot be drawn.',
                                            { count: unknownMaps(row) },
                                        )
                                    }}
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ row.points_done }} / {{ row.points_total }}
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ `${row.execution_time}${t('s')}` }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDateTime(row.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="Inbox"
                :title="t('No imports yet.')"
                :description="t('Paste a session above to import it.')"
            />

            <Pagination :paginator="imports" />
        </template>

        <template v-else>
            <div class="wow-panel mb-6 flex flex-col p-6">
                <h2 class="font-display text-lg tracking-wide text-foreground">
                    {{ t('Import every session at once') }}
                </h2>
                <hr class="wow-divider my-4" />
                <p class="mb-4 text-sm text-muted-foreground">
                    {{
                        t(
                            'Upload the addon save file and every session in it is imported at once — no copying needed.',
                        )
                    }}
                </p>

                <div
                    class="mb-6 rounded-md border border-border/60 bg-muted/30 p-3"
                >
                    <p
                        class="mb-1 font-mono text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('Where the file lives') }}
                    </p>
                    <p
                        class="font-mono text-[11px] leading-relaxed break-all text-foreground"
                    >
                        World of
                        Warcraft/_classic_era_/WTF/Account/&lt;ACCOUNT&gt;/SavedVariables/WhereIveBeen.lua
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            t(
                                'Use _retail_ or _classic_ instead of _classic_era_ for other clients. Log out of the game first, or the file will be missing the latest session.',
                            )
                        }}
                    </p>
                </div>

                <Form
                    v-bind="ImportController.storeFile.form()"
                    :reset-on-success="['file']"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-col justify-end gap-4"
                    @success="dropZone?.reset()"
                >
                    <div class="grid gap-2">
                        <FileDropZone
                            ref="dropZone"
                            name="file"
                            :max-size-mb="fileLimitMb"
                            :invalid="Boolean(errors.file)"
                        />
                        <InputError :message="errors.file" />
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing"
                        data-test="import-file-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('Upload file') }}
                    </Button>
                </Form>
            </div>

            <div v-if="batches.data.length" class="wow-panel overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b border-border/70 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ t('File') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Status') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Found') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Queued') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Skipped') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('Created') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="batch in batches.data"
                            :key="batch.id"
                            class="border-b border-border/40 align-top last:border-0"
                        >
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ batch.filename }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="status"
                                    :class="batchStateClass[batch.state]"
                                >
                                    {{ batchStateLabel[batch.state] }}
                                </Badge>

                                <div
                                    v-if="batch.error_code"
                                    class="mt-2 max-w-xs text-xs"
                                >
                                    <p class="text-destructive">
                                        {{
                                            importErrorHint(
                                                batch.error_code,
                                                batch.error_context,
                                            )?.title
                                        }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{
                                            importErrorHint(
                                                batch.error_code,
                                                batch.error_context,
                                            )?.hint
                                        }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ batch.sessions_found }}
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ batch.sessions_queued }}

                                <p
                                    v-if="batch.state === 'importing'"
                                    class="mt-1 font-mono text-[11px] text-muted-foreground tabular-nums"
                                >
                                    {{ batch.sessions_finished }} /
                                    {{ batch.sessions_queued }}
                                </p>

                                <p
                                    v-else-if="batch.sessions_failed"
                                    class="mt-1 font-mono text-[11px] text-destructive tabular-nums"
                                >
                                    {{
                                        t(':count failed', {
                                            count: batch.sessions_failed,
                                        })
                                    }}
                                </p>
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ batch.sessions_skipped }}

                                <ul
                                    v-if="batch.skipped?.length"
                                    class="mt-2 space-y-1 text-xs text-muted-foreground"
                                >
                                    <li
                                        v-for="skip in batch.skipped"
                                        :key="skip.session_id"
                                    >
                                        {{ skip.character ?? t('Unnamed') }} ·
                                        {{
                                            skipReasonLabel[skip.reason] ??
                                            skip.reason
                                        }}
                                    </li>
                                </ul>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ formatDateTime(batch.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                :icon="FileUp"
                :title="t('No files uploaded yet.')"
                :description="
                    t(
                        'Upload the addon save file above to import every session in it.',
                    )
                "
            />

            <Pagination :paginator="batches" />
        </template>
    </div>
</template>
