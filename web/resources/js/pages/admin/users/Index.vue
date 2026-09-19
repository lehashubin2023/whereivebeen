<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/AdminUserController';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
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
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/datetime';
import { t } from '@/lib/i18n';
import type { AdminUserRow, AdminUsersPaginator } from '@/types/admin-user';

const props = defineProps<{
    users: AdminUsersPaginator;
    search: string | null;
    currentUserId: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Users'), href: '/admin/users' }],
    },
});

const page = usePage();

const query = ref(props.search ?? '');

const searchError = computed(() => page.props.errors?.search);

function roleLabel(row: AdminUserRow): string {
    if (row.is_main_admin) {
        return t('Main admin');
    }

    return row.is_admin ? t('Admin') : t('User');
}
</script>

<template>
    <Head :title="t('Users')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Users') }}</h1>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <form
                method="get"
                action="/admin/users"
                class="flex flex-col gap-1"
            >
                <div class="flex gap-2">
                    <Input
                        v-model="query"
                        type="search"
                        name="search"
                        :placeholder="t('Search by email')"
                        :aria-invalid="Boolean(searchError)"
                        class="w-64"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="!query.trim()"
                        data-test="search-users-button"
                    >
                        {{ t('Search') }}
                    </Button>
                    <Button v-if="search" as-child variant="ghost">
                        <Link href="/admin/users">{{ t('Reset') }}</Link>
                    </Button>
                </div>
                <InputError :message="searchError" />
            </form>

            <Button as-child>
                <Link href="/admin/users/create">{{ t('New user') }}</Link>
            </Button>
        </div>

        <div v-if="users.data.length" class="wow-panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border/70 text-left text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('Email') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('Role') }}</th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Sessions') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Verified') }}
                        </th>
                        <th class="px-4 py-3 font-medium">
                            {{ t('Created') }}
                        </th>
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
                                {{ t('(you)') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="status"
                                :class="
                                    row.is_admin
                                        ? 'text-gold'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ roleLabel(row) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">{{ row.sessions_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ row.email_verified_at ? t('Yes') : t('No') }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ formatDateTime(row.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    v-if="row.can_edit"
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    :data-test="`edit-user-${row.id}`"
                                >
                                    <Link :href="`/admin/users/${row.id}/edit`">
                                        <Pencil class="size-3.5" />
                                        {{ t('Edit') }}
                                    </Link>
                                </Button>

                                <Dialog v-if="row.can_delete">
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            :data-test="`delete-user-${row.id}`"
                                        >
                                            {{ t('Delete') }}
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
                                            v-slot="{ errors, processing }"
                                        >
                                            <DialogHeader class="space-y-3">
                                                <DialogTitle>
                                                    {{
                                                        t('Delete :email?', {
                                                            email: row.email,
                                                        })
                                                    }}
                                                </DialogTitle>
                                                <DialogDescription>
                                                    {{
                                                        t(
                                                            'All sessions, routes and reports belonging to this user will be permanently deleted. This cannot be undone.',
                                                        )
                                                    }}
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div class="grid gap-2">
                                                <Label
                                                    :for="`confirmation-${row.id}`"
                                                >
                                                    {{
                                                        t(
                                                            'Type the email of the user to confirm the deletion.',
                                                        )
                                                    }}
                                                </Label>
                                                <Input
                                                    :id="`confirmation-${row.id}`"
                                                    name="confirmation"
                                                    autocomplete="off"
                                                    :placeholder="row.email"
                                                    :aria-invalid="
                                                        Boolean(
                                                            errors.confirmation,
                                                        )
                                                    "
                                                    :data-test="`confirmation-${row.id}`"
                                                />
                                                <InputError
                                                    :message="
                                                        errors.confirmation
                                                    "
                                                />
                                            </div>

                                            <DialogFooter class="gap-2">
                                                <DialogClose as-child>
                                                    <Button variant="secondary">
                                                        {{ t('Cancel') }}
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    :disabled="processing"
                                                    :data-test="`confirm-delete-user-${row.id}`"
                                                >
                                                    {{ t('Delete user') }}
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
            <p class="text-muted-foreground">{{ t('No users found.') }}</p>
        </div>

        <Pagination :paginator="users" />
    </div>
</template>
