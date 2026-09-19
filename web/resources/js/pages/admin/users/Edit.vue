<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminUserController from '@/actions/App/Http/Controllers/Admin/AdminUserController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import type { AdminUserForm } from '@/types/admin-user';

const props = defineProps<{
    user: AdminUserForm;
    isSelf: boolean;
    canAssignAdmin: boolean;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Users'), href: '/admin/users' }],
    },
});

const canEditEmail = computed(() => !props.user.is_main_admin);

const canEditRole = computed(
    () => props.canAssignAdmin && !props.user.is_main_admin && !props.isSelf,
);

const roleHint = computed(() => {
    if (props.user.is_main_admin) {
        return t('The main administrator account type cannot be changed.');
    }

    if (props.isSelf) {
        return t('You cannot change your own account type.');
    }

    if (!props.canAssignAdmin) {
        return t(
            'Only the main administrator can manage administrator access.',
        );
    }

    return null;
});
</script>

<template>
    <Head :title="t('Edit :email', { email: props.user.email })" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Edit user') }}</h1>

        <div class="wow-panel max-w-xl p-6">
            <h2 class="font-display text-lg tracking-wide text-foreground">
                {{ t('Edit user') }}
            </h2>
            <hr class="wow-divider my-4" />

            <Form
                v-bind="AdminUserController.update.form(props.user.id)"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-2">
                    <Label for="email">{{ t('Email address') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        :default-value="props.user.email"
                        :readonly="!canEditEmail"
                        :aria-invalid="Boolean(errors.email)"
                    />
                    <p
                        v-if="!canEditEmail"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            t('The main administrator email cannot be changed.')
                        }}
                    </p>
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('New password') }}</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        :placeholder="t('Leave blank to keep the current one')"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">
                        {{ t('Confirm password') }}
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        autocomplete="new-password"
                        :placeholder="t('Confirm new password')"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="is_admin"
                        name="is_admin"
                        value="1"
                        :default-value="props.user.is_admin"
                        :disabled="!canEditRole"
                    />
                    <Label for="is_admin">{{ t('Administrator') }}</Label>
                </div>
                <p v-if="roleHint" class="text-xs text-muted-foreground">
                    {{ roleHint }}
                </p>
                <input
                    v-if="!canEditRole"
                    type="hidden"
                    name="is_admin"
                    :value="props.user.is_admin ? '1' : '0'"
                />
                <InputError :message="errors.is_admin" />

                <div class="mt-2 flex items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="update-user-button"
                    >
                        <Spinner v-if="processing" />
                        {{ t('Save') }}
                    </Button>
                    <Button as-child variant="secondary">
                        <Link href="/admin/users">{{ t('Cancel') }}</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
