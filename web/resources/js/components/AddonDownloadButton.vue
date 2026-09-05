<script setup lang="ts">
import { Download } from '@lucide/vue';
import { computed } from 'vue';
import type { AddonDownload } from '@/types';

type Props = {
    addon: AddonDownload;
};

const props = defineProps<Props>();

const href = computed(() => props.addon.url ?? '#');

const meta = computed(() => {
    if (!props.addon.available) {
        return 'Not built yet';
    }

    const parts: string[] = [];

    if (props.addon.version) {
        parts.push(`v${props.addon.version}`);
    }

    if (props.addon.size) {
        parts.push(`${Math.max(1, Math.round(props.addon.size / 1024))} KB`);
    }

    return parts.join(' · ');
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <a :href="href" download class="wow-btn text-sm">
            <Download class="size-4" />
            Download addon
        </a>
        <span v-if="meta" class="text-xs text-muted-foreground">{{
            meta
        }}</span>
    </div>
</template>
