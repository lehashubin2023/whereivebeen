<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import IssueReportController from '@/actions/App/Http/Controllers/IssueReportController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';

defineOptions({
    layout: {
        breadcrumbs: [{ title: t('Report a problem'), href: '/issue-report' }],
    },
});
</script>

<template>
    <Head :title="t('Report a problem')" />

    <div class="flex flex-1 flex-col p-4 md:p-8">
        <h1 class="sr-only">{{ t('Report a problem') }}</h1>

        <div class="wow-panel max-w-2xl p-6">
            <h2 class="text-gold font-display text-lg tracking-wide">
                {{ t('Report a problem') }}
            </h2>
            <hr class="wow-divider my-4" />
            <p class="mb-6 text-sm text-muted-foreground">
                {{
                    t(
                        'Ran into an issue or have a question? Describe it below and we will get back to you.',
                    )
                }}
            </p>

            <Form
                v-bind="IssueReportController.store.form()"
                :reset-on-success="['message']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-2">
                    <Label for="message">{{ t('Your message') }}</Label>
                    <Textarea
                        id="message"
                        name="message"
                        required
                        rows="8"
                        :placeholder="
                            t('Describe the problem or ask your question…')
                        "
                        class="min-h-48"
                    />
                    <InputError :message="errors.message" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    data-test="submit-issue-report-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('Send') }}
                </Button>
            </Form>
        </div>
    </div>
</template>
