<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: t('Verify email'),
        description: t(
            'Confirm your email address by clicking the link we just sent you',
        ),
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="t('Verify email')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{
            t(
                'A new verification link has been sent to the email address you provided during registration.',
            )
        }}
    </div>

    <div class="space-y-6 text-center">
        <Form v-bind="send.form()" v-slot="{ processing }">
            <Button
                class="w-full"
                :disabled="processing"
                data-test="resend-verification-email-button"
            >
                <Spinner v-if="processing" />
                {{ t('Resend verification email') }}
            </Button>
        </Form>

        <TextLink
            :href="logout()"
            method="post"
            as="button"
            class="mx-auto block text-sm"
        >
            {{ t('Log out') }}
        </TextLink>
    </div>
</template>
