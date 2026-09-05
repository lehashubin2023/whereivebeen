<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Import', href: '/game-session/import' }],
    },
});
</script>

<template>
    <Head title="Import" />

    <div class="flex flex-1 justify-center p-4 md:p-8">
        <div class="wow-panel w-full max-w-2xl p-6 md:p-8">
            <h1
                class="text-gold font-display text-2xl font-semibold tracking-wide"
            >
                Import Session
            </h1>
            <hr class="wow-divider my-4" />
            <p class="mb-6 text-sm text-muted-foreground">
                Paste the session exported from the addon and submit it for
                processing.
            </p>

            <Form
                action="/game-session/import"
                method="post"
                :reset-on-success="['game_session']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-4"
            >
                <div class="grid gap-2">
                    <Label for="game_session">Session data</Label>
                    <Textarea
                        id="game_session"
                        name="game_session"
                        required
                        autofocus
                        rows="12"
                        placeholder="Paste exported session JSON…"
                        class="min-h-64 font-mono"
                    />
                    <InputError :message="errors.game_session" />
                </div>

                <Button type="submit" class="w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Import
                </Button>
            </Form>
        </div>
    </div>
</template>
