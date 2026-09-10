<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import AdminUserController from '@/actions/App/Http/Controllers/AdminUserController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { AdminUsersPaginator } from '@/types/admin-user';

defineProps<{
    users: AdminUsersPaginator;
    search: string | null;
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: '/admin/users' }],
    },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">Users</h1>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <form method="get" action="/admin/users" class="flex gap-2">
                <Input
                    type="search"
                    name="search"
                    :default-value="search ?? ''"
                    placeholder="Search by email"
                    class="w-64"
                />
                <Button type="submit" variant="outline">Search</Button>
            </form>

            <Button as-child>
                <Link href="/admin/users/create">New user</Link>
            </Button>
        </div>

        <div v-if="users.data.length" class="wow-panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Sessions</th>
                        <th class="px-4 py-3 font-medium">Verified</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in users.data"
                        :key="row.id"
                        class="border-b border-border/40 last:border-0"
                    >
                        <td class="px-4 py-3">
                            {{ row.email }}
                            <span
                                v-if="row.id === currentUserId"
                                class="ml-2 text-xs text-muted-foreground"
                            >
                                (you)
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    row.is_admin
                                        ? 'text-gold border-border bg-muted'
                                        : 'border-border bg-muted text-muted-foreground'
                                "
                            >
                                {{ row.is_admin ? 'Admin' : 'User' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">{{ row.sessions_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.email_verified_at ? 'Yes' : 'No' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDate(row.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    :data-test="`edit-user-${row.id}`"
                                >
                                    <Link :href="`/admin/users/${row.id}/edit`">
                                        <Pencil class="size-3.5" />
                                        Edit
                                    </Link>
                                </Button>

                                <Dialog v-if="row.id !== currentUserId">
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            :data-test="`delete-user-${row.id}`"
                                        >
                                            Delete
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <Form
                                            v-bind="
                                                AdminUserController.destroy.form(
                                                    row.id,
                                                )
                                            "
                                            :options="{ preserveScroll: true }"
                                            class="space-y-6"
                                            v-slot="{ processing }"
                                        >
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle>
                                                    Delete {{ row.email }}?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    All sessions, routes and
                                                    reports belonging to this
                                                    user will be permanently
                                                    deleted. This cannot be
                                                    undone.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                    :data-test="`confirm-delete-user-${row.id}`"
                                                >
                                                    Delete user
                                                </Button>
                                            </DialogFooter>
                                        </Form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="wow-panel flex flex-col items-center justify-center gap-3 p-12 text-center"
        >
            <p class="text-muted-foreground">No users found.</p>
        </div>

        <div
            v-if="users.prev_page_url || users.next_page_url"
            class="mt-4 flex justify-between"
        >
            <Link
                v-if="users.prev_page_url"
                :href="users.prev_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Previous
            </Link>
            <span v-else />
            <Link
                v-if="users.next_page_url"
                :href="users.next_page_url"
                class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
            >
                Next
            </Link>
        </div>
    </div>
</template>
