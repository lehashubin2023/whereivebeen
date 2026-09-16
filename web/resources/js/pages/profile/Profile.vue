<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { locale, t } from '@/lib/i18n';
import { edit } from '@/routes/profile';
import type { LocaleOption } from '@/types';

type Props = {
    passwordRules: string;
    locales: LocaleOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: t('Profile'),
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

function changeLocale(value: unknown): void {
    if (typeof value !== 'string' || value === locale) {
        return;
    }

    router.patch(
        ProfileController.updateLocale.url(),
        { locale: value },
        { onSuccess: () => window.location.reload() },
    );
}
</script>

<template>
    <Head :title="t('Profile')" />

    <h1 class="sr-only">{{ t('Profile') }}</h1>

    <div class="flex flex-col space-y-3">
        <Heading
            variant="small"
            :title="t('Profile')"
            :description="t('Update your email address')"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Input
                    id="email"
                    type="email"
                    class="block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    :aria-label="t('Email address')"
                    :placeholder="t('Email address')"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    {{ t('Save') }}
                </Button>
            </div>
        </Form>
    </div>

    <div class="space-y-3">
        <Heading
            variant="small"
            :title="t('Language')"
            :description="t('Choose the language of the interface')"
        />

        <div class="grid max-w-xs gap-2">
            <Select :default-value="locale" @update:model-value="changeLocale">
                <SelectTrigger id="locale" data-test="locale-select">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in props.locales"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
    </div>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('Update password')"
            :description="
                t(
                    'Ensure your account is using a long, random password to stay secure',
                )
            "
        />

        <Form
            v-bind="ProfileController.updatePassword.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">{{
                    t('Current password')
                }}</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="t('Current password')"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">{{ t('New password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t('New password')"
                    :passwordrules="props.passwordRules"
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
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t('Confirm password')"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    {{ t('Save') }}
                </Button>
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
