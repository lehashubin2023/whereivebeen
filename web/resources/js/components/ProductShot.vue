<script setup lang="ts">
import { ImageOff } from '@lucide/vue';
import { ref } from 'vue';
import { t } from '@/lib/i18n';

type Props = {
    src: string;
    alt: string;
    caption: string;
    ratio?: string;
};

withDefaults(defineProps<Props>(), {
    ratio: '16 / 10',
});

const failed = ref(false);
</script>

<template>
    <figure class="flex flex-col gap-2">
        <div
            class="wow-map-frame overflow-hidden"
            :style="{ aspectRatio: ratio }"
        >
            <img
                v-if="!failed"
                :src="src"
                :alt="alt"
                loading="lazy"
                class="h-full w-full object-cover object-top"
                @error="failed = true"
            />

            <div
                v-else
                class="flex h-full w-full flex-col items-center justify-center gap-2 bg-muted/40 text-center"
            >
                <ImageOff class="size-5 text-muted-foreground" />
                <span
                    class="font-mono text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('Screenshot pending') }}
                </span>
                <code
                    class="border-0 bg-transparent font-mono text-[10px] text-muted-foreground"
                >
                    {{ src }}
                </code>
            </div>
        </div>

        <figcaption class="text-xs text-muted-foreground">
            {{ caption }}
        </figcaption>
    </figure>
</template>
