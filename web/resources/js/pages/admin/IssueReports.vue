<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AdminIssueReportController from '@/actions/App/Http/Controllers/AdminIssueReportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
            { title: 'Problem reports', href: '/admin/issue-reports' },
        ],
    },
});

const statusClass: Record<IssueReportStatus, string> = {
    new: 'bg-muted text-muted-foreground border-border',
    resolved: 'bg-emerald-900/40 text-emerald-300 border-emerald-700/50',
};

const statusLabel: Record<IssueReportStatus, string> = {
    new: 'New',
    resolved: 'Resolved',
};

const filters: { label: string; value: IssueReportStatus | null }[] = [
    { label: 'All', value: null },
    { label: 'New', value: 'new' },
    { label: 'Resolved', value: 'resolved' },
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
    return status === 'new' ? 'Mark resolved' : 'Reopen';
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Problem reports" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">Problem reports</h1>

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
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Message</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in reports.data"
                        :key="row.id"
                        class="border-b border-border/40 last:border-0"
                    >
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.user_email ?? '—' }}
                        </td>
                        <td class="max-w-md px-4 py-3 whitespace-pre-wrap">
                            {{ row.message }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="statusClass[row.status]"
                            >
                                {{ statusLabel[row.status] }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(row.created_at) }}
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
            <p class="text-muted-foreground">No reports yet.</p>
        </div>

        <div
            v-if="reports.prev_page_url || reports.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="reports.prev_page_url"
                :href="reports.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Previous
            </Link>
            <span v-else />
            <Link
                v-if="reports.next_page_url"
                :href="reports.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Next
            </Link>
        </div>
    </div>
</template>
