<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { t } from '@/lib/i18n';
import type { Paginator } from '@/types';

defineProps<{
    paginator: Paginator<unknown>;
}>();
</script>

<template>
    <div
        v-if="paginator.last_page > 1"
        class="mt-4 flex items-center justify-between gap-4"
    >
        <Link
            v-if="paginator.prev_page_url"
            :href="paginator.prev_page_url"
            preserve-scroll
            class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
        >
            {{ t('Previous') }}
        </Link>
        <span v-else />

        <span class="text-xs text-muted-foreground tabular-nums">
            {{
                t('Page :current of :last', {
                    current: paginator.current_page,
                    last: paginator.last_page,
                })
            }}
        </span>

        <Link
            v-if="paginator.next_page_url"
            :href="paginator.next_page_url"
            preserve-scroll
            class="rounded-md border border-border/60 px-3 py-1 text-sm text-muted-foreground hover:bg-muted"
        >
            {{ t('Next') }}
        </Link>
        <span v-else />
    </div>
</template>
