<script setup lang="ts">
import { Upload } from '@lucide/vue';
import { computed, ref } from 'vue';
import { t } from '@/lib/i18n';

type Props = {
    name: string;
    accept?: string;
    maxSizeMb?: number;
};

const props = withDefaults(defineProps<Props>(), {
    accept: '.lua,.txt',
    maxSizeMb: 32,
});

const input = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
const selected = ref<File | null>(null);
const localError = ref<string | null>(null);

const extensions = computed(() =>
    props.accept.split(',').map((item) => item.trim().replace(/^\./, '')),
);

const sizeLabel = computed(() => {
    if (!selected.value) {
        return '';
    }

    const kb = selected.value.size / 1024;

    return kb > 1024
        ? `${(kb / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(kb))} KB`;
});

function validate(file: File): string | null {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!extensions.value.includes(extension)) {
        return t('Pick a .lua file — this one is .:extension.', {
            extension: extension || '?',
        });
    }

    if (file.size > props.maxSizeMb * 1024 * 1024) {
        return t('The file is larger than :size MB.', {
            size: props.maxSizeMb,
        });
    }

    if (file.size === 0) {
        return t('The file is empty.');
    }

    return null;
}

function assign(file: File | null): void {
    if (!file) {
        return;
    }

    const problem = validate(file);

    if (problem) {
        localError.value = problem;
        selected.value = null;
        clearInput();

        return;
    }

    localError.value = null;
    selected.value = file;
}

function clearInput(): void {
    if (input.value) {
        input.value.value = '';
    }
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;

    if (file && input.value) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.value.files = transfer.files;
    }

    assign(file);
}

function onChange(event: Event): void {
    assign((event.target as HTMLInputElement).files?.[0] ?? null);
}

function reset(): void {
    selected.value = null;
    localError.value = null;
    clearInput();
}

defineExpose({ reset });
</script>

<template>
    <div class="flex flex-col gap-2">
        <label
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border border-dashed px-6 py-8 text-center transition-colors"
            :class="
                dragging
                    ? 'border-primary bg-accent'
                    : 'border-border hover:border-primary/60'
            "
            @dragover.prevent="dragging = true"
            @dragenter.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="input"
                type="file"
                :name="name"
                :accept="accept"
                class="sr-only"
                @change="onChange"
            />

            <Upload class="size-5 text-muted-foreground" />

            <span v-if="selected" class="font-mono text-xs text-foreground">
                {{ selected.name }}
                <span class="text-muted-foreground">· {{ sizeLabel }}</span>
            </span>
            <span v-else class="text-sm text-foreground">
                {{ t('Drop WhereIveBeen.lua here or click to pick it') }}
            </span>

            <span class="text-xs text-muted-foreground">
                {{ t('Up to :size MB', { size: maxSizeMb }) }}
            </span>
        </label>

        <button
            v-if="selected"
            type="button"
            class="self-start text-xs text-muted-foreground underline-offset-4 hover:underline"
            @click="reset"
        >
            {{ t('Pick another file') }}
        </button>

        <p v-if="localError" class="text-xs text-destructive">
            {{ localError }}
        </p>
    </div>
</template>
