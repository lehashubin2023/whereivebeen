<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import AdminIssueReportController from '@/actions/App/Http/Controllers/AdminIssueReportController';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/datetime';
import { t } from '@/lib/i18n';
import type {
    IssueReportStatus,
    IssueReportsPaginator,
} from '@/types/issue-report';

const props = defineProps<{
    reports: IssueReportsPaginator;
    status: IssueReportStatus | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: t('Problem reports'), href: '/admin/issue-reports' },
        ],
    },
});

const statusClass: Record<IssueReportStatus, string> = {
    new: 'text-gold',
    resolved: 'text-emerald-400',
};

const statusLabel: Record<IssueReportStatus, string> = {
    new: t('New'),
    resolved: t('Resolved'),
};

const filters: { label: string; value: IssueReportStatus | null }[] = [
    { label: t('All'), value: null },
    { label: t('New'), value: 'new' },
    { label: t('Resolved'), value: 'resolved' },
];

function filterHref(value: IssueReportStatus | null): string {
    return value
        ? `/admin/issue-reports?status=${value}`
        : '/admin/issue-reports';
}

function isActiveFilter(value: IssueReportStatus | null): boolean {
    return (props.status ?? null) === value;
}

function nextStatus(status: IssueReportStatus): IssueReportStatus {
    return status === 'new' ? 'resolved' : 'new';
}

function actionLabel(status: IssueReportStatus): string {
    return status === 'new' ? t('Mark resolved') : t('Reopen');
}

const MESSAGE_PREVIEW_LENGTH = 100;

const expanded = ref<number | null>(null);

function isTruncated(message: string): boolean {
    return message.length > MESSAGE_PREVIEW_LENGTH;
}

function messagePreview(message: string): string {
    return `${message.slice(0, MESSAGE_PREVIEW_LENGTH).trimEnd()}…`;
}

function toggle(id: number): void {
    expanded.value = expanded.value === id ? null : id;
}
</script>

<template>
    <Head :title="t('Problem reports')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Problem reports') }}</h1>

        <div class="mb-6 flex items-center gap-2">
            <Link
                v-for="filter in filters"
                :key="filter.label"
                :href="filterHref(filter.value)"
                class="rounded-md border px-3 py-1 text-sm"
                :class="
                    isActiveFilter(filter.value)
                        ? 'text-gold border-border bg-muted'
                        : 'border-border/60 text-muted-foreground hover:bg-muted'
                "
            >
                {{ filter.label }}
            </Link>
        </div>

        <div v-if="reports.data.length" class="wow-panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('User') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Message') }}
                        </th>
                        <th class="px-4 py-3 font-medium">{{ t('Status') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Created') }}
                        </th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in reports.data"
                        :key="row.id"
                        class="border-b border-border/40 align-top last:border-0"
                    >
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.user_email ?? '—' }}
                        </td>
                        <td class="max-w-md px-4 py-3 align-top">
                            <p class="whitespace-pre-wrap">
                                {{
                                    expanded === row.id ||
                                    !isTruncated(row.message)
                                        ? row.message
                                        : messagePreview(row.message)
                                }}
                            </p>

                            <button
                                v-if="isTruncated(row.message)"
                                type="button"
                                class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-4 hover:underline"
                                :data-test="`toggle-message-${row.id}`"
                                @click="toggle(row.id)"
                            >
                                <ChevronDown
                                    class="size-3 transition-transform"
                                    :class="
                                        expanded === row.id ? 'rotate-180' : ''
                                    "
                                />
                                {{ t('Full message') }}
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="status"
                                :class="statusClass[row.status]"
                            >
                                {{ statusLabel[row.status] }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDateTime(row.created_at) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Form
                                v-bind="
                                    AdminIssueReportController.updateStatus.form(
                                        row.id,
                                    )
                                "
                                :options="{ preserveScroll: true }"
                                v-slot="{ processing }"
                            >
                                <input
                                    type="hidden"
                                    name="status"
                                    :value="nextStatus(row.status)"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                    :data-test="`update-status-${row.id}`"
                                >
                                    {{ actionLabel(row.status) }}
                                </Button>
                            </Form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="wow-panel flex flex-col items-center justify-center gap-3 p-12 text-center"
        >
            <p class="text-muted-foreground">{{ t('No reports yet.') }}</p>
        </div>

        <Pagination :paginator="reports" />
    </div>
</template>
