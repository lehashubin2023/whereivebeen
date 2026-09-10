<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AdminUserController from '@/actions/App/Http/Controllers/AdminUserController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: '/admin/users' },
            { title: 'New user', href: '/admin/users/create' },
        ],
    },
});
</script>

<template>
    <Head title="New user" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">New user</h1>

        <div class="wow-panel max-w-xl p-6">
            <h2 class="text-gold font-display text-lg tracking-wide">
                New user
            </h2>
            <hr class="wow-divider my-4" />

            <Form
                v-bind="AdminUserController.store.form()"
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        autofocus
                        id="email"
                        type="email"
                        name="email"
                        required
                        autocomplete="off"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirm password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox id="is_admin" name="is_admin" value="1" />
                    <Label for="is_admin">Administrator</Label>
                </div>
                <InputError :message="errors.is_admin" />

                <div class="mt-2 flex items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="create-user-button"
                    >
                        <Spinner v-if="processing" />
                        Create user
                    </Button>
                    <Button as-child variant="secondary">
                        <Link href="/admin/users">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
