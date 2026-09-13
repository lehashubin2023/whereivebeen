<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Inbox } from '@lucide/vue';
import { ref } from 'vue';
import GameSessionController from '@/actions/App/Http/Controllers/GameSessionController';
import EmptyState from '@/components/EmptyState.vue';
import FileDropZone from '@/components/import/FileDropZone.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { locale, t } from '@/lib/i18n';
import { importErrorHint } from '@/lib/importErrors';
import type { ImportBatchRow, ImportRow, ImportStatus } from '@/types';

interface ImportsPaginator {
    data: ImportRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{ imports: ImportsPaginator; batches: ImportBatchRow[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Imports'), href: '/game-session/imports' }],
    },
});

const expanded = ref<number | null>(null);

const statusClass: Record<ImportStatus, string> = {
    new: 'bg-muted text-muted-foreground border-border',
    in_process: 'bg-accent text-accent-foreground border-accent',
    completed: 'bg-emerald-950/60 text-emerald-300 border-emerald-800/60',
    failed: 'bg-destructive/15 text-destructive border-destructive/40',
};

const statusLabel: Record<ImportStatus, string> = {
    new: t('New'),
    in_process: t('In process'),
    completed: t('Completed'),
    failed: t('Failed'),
};

const batchStatusLabel: Record<string, string> = {
    new: t('Queued'),
    parsing: t('Reading file'),
    dispatched: t('Sessions queued'),
    failed: t('Failed'),
};

const skipReasonLabel: Record<string, string> = {
    no_points: t('No recorded points'),
    no_character: t('No character name'),
    no_session_id: t('No session id'),
    no_start_time: t('No start time'),
    too_large: t('Too large to import'),
};

const outcomeLabel: Record<string, string> = {
    created: t('Added'),
    replaced: t('Replaced'),
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString(locale) : '—';
}

function unknownMaps(row: ImportRow): number {
    return Object.keys(row.warnings?.unknown_maps ?? {}).length;
}

function toggle(id: number): void {
    expanded.value = expanded.value === id ? null : id;
}
</script>

<template>
    <Head :title="t('Imports')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Imports') }}</h1>

        <div class="mb-6 grid gap-4 lg:grid-cols-2">
            <div class="wow-panel flex flex-col p-6">
                <h2 class="font-semibold tracking-wide text-foreground">
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
                    v-bind="GameSessionController.importMethod.form()"
                    :reset-on-success="['game_session']"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-col gap-4"
                >
                    <div class="grid flex-1 gap-2">
                        <Label for="game_session">{{
                            t('Session data')
                        }}</Label>
                        <Textarea
                            id="game_session"
                            name="game_session"
                            required
                            rows="8"
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

            <div class="wow-panel flex flex-col p-6">
                <h2 class="font-semibold tracking-wide text-foreground">
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
                    v-bind="GameSessionController.importFile.form()"
                    v-slot="{ errors, processing }"
                    class="flex flex-1 flex-col justify-end gap-4"
                >
                    <div class="grid gap-2">
                        <FileDropZone name="file" />
                        <InputError :message="errors.file" />
                    </div>

                    <Button
                        type="submit"
                        variant="outline"
                        class="w-full"
                        :disabled="processing"
                        data-test="import-file-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('Upload file') }}
                    </Button>
                </Form>
            </div>
        </div>

        <div v-if="batches.length" class="wow-panel mb-6 overflow-x-auto">
            <h2
                class="border-b border-border/70 px-4 py-3 font-semibold tracking-wide text-foreground"
            >
                {{ t('Uploaded files') }}
            </h2>

            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('File') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('Status') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Sessions') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Created') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="batch in batches"
                        :key="batch.id"
                        class="border-b border-border/40 align-top last:border-0"
                    >
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ batch.filename }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge variant="outline">
                                {{
                                    batchStatusLabel[batch.status] ??
                                    batch.status
                                }}
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
                        <td class="px-4 py-3">
                            <span class="tabular-nums">
                                {{
                                    t(
                                        ':found found · :queued queued · :skipped skipped',
                                        {
                                            found: batch.sessions_found,
                                            queued: batch.sessions_queued,
                                            skipped: batch.sessions_skipped,
                                        },
                                    )
                                }}
                            </span>

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
                            {{ formatDate(batch.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
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
                        <th class="px-4 py-3 font-medium">{{ t('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('Points') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('Time') }}</th>
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
                                v-if="row.outcome"
                                class="mt-1 font-mono text-[11px] text-muted-foreground"
                            >
                                {{ outcomeLabel[row.outcome] ?? row.outcome }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
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
                            {{ formatDate(row.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EmptyState
            v-else
            :icon="Inbox"
            :title="t('No imports yet.')"
            :description="
                t('Paste a session above or upload the addon save file.')
            "
        />

        <div
            v-if="imports.prev_page_url || imports.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="imports.prev_page_url"
                :href="imports.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                {{ t('Previous') }}
            </Link>
            <span v-else />
            <Link
                v-if="imports.next_page_url"
                :href="imports.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                {{ t('Next') }}
            </Link>
        </div>
    </div>
</template>
