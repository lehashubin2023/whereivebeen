<script setup lang="ts">
import { X } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { EVENT_COLORS, EVENT_ICONS } from '@/lib/eventStyles';
import { t } from '@/lib/i18n';
import type { SessionEvent } from '@/types';

const props = defineProps<{
    event: SessionEvent | null;
    loading: boolean;
    error: string | null;
}>();

defineEmits<{ close: [] }>();

const icon = computed<Component | null>(() =>
    props.event ? EVENT_ICONS[props.event.type] : null,
);

const accent = computed(() =>
    props.event ? EVENT_COLORS[props.event.type] : null,
);

function formatTime(value: string | null): string {
    return value ? new Date(value).toLocaleTimeString() : '';
}
</script>

<template>
    <div class="wow-panel relative w-56 px-4 pt-8 pb-3">
        <span
            class="wow-medallion text-gold"
            :style="accent ? { color: accent } : undefined"
        >
            <component :is="icon" v-if="icon" class="size-5" />
        </span>

        <button
            type="button"
            class="absolute top-1.5 right-1.5 rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-primary"
            :title="t('Close')"
            @click="$emit('close')"
        >
            <X class="size-3.5" />
        </button>

        <div v-if="loading" class="flex flex-col gap-2">
            <Skeleton class="h-4 w-24" />
            <Skeleton class="h-3 w-full" />
        </div>

        <p v-else-if="error" class="text-center text-xs text-destructive">
            {{ error }}
        </p>

        <template v-else-if="event">
            <p
                class="text-center text-sm font-semibold text-foreground"
                :style="accent ? { color: accent } : undefined"
            >
                {{ event.label }}
            </p>
            <p
                v-if="event.time"
                class="mt-0.5 text-center text-xs text-muted-foreground"
            >
                {{ formatTime(event.time) }}
            </p>

            <dl v-if="event.details.length" class="mt-3 flex flex-col gap-1">
                <div
                    v-for="detail in event.details"
                    :key="detail.label"
                    class="flex items-baseline justify-between gap-3 text-xs"
                >
                    <dt class="shrink-0 text-muted-foreground">
                        {{ detail.label }}
                    </dt>
                    <dd class="truncate text-right text-foreground">
                        {{ detail.value }}
                    </dd>
                </div>
            </dl>
        </template>
    </div>
</template>
