<script setup lang="ts">
import { Minus, Plus, RotateCcw } from '@lucide/vue';
import { useElementSize, useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';
import EventPopup from '@/components/map/EventPopup.vue';
import { useSessionEvent } from '@/composables/useSessionEvent';
import {
    EVENT_COLORS,
    EVENT_ICONS,
    EVENT_LABELS,
    EVENT_ORDER,
    EVENT_OUTLINE,
} from '@/lib/eventStyles';
import type { EventSlug, RoutePoint, RouteState } from '@/types';

type State = RouteState;
type Point = RoutePoint;

const STATE_COLORS: Record<State, { line: string; dot: string }> = {
    ground: { line: '#f5c542', dot: '#f7cf5a' },
    mounted: { line: '#3b82f6', dot: '#60a5fa' },
    flying: { line: '#a855f7', dot: '#c084fc' },
};

const LEGEND: { state: State; label: string }[] = [
    { state: 'ground', label: 'On foot' },
    { state: 'mounted', label: 'Mounted' },
    { state: 'flying', label: 'Flying' },
];

const props = defineProps<{
    image: string;
    points: Point[];
    gameSessionId: number;
}>();

const viewport = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);

const { width: viewportWidth, height: viewportHeight } =
    useElementSize(viewport);
const { width: stageWidth, height: stageHeight } = useElementSize(stage);

const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);

const MIN_SCALE = 1;
const MAX_SCALE = 16;

const transform = computed(
    () =>
        `translate(${translateX.value}px, ${translateY.value}px) scale(${scale.value})`,
);

const segments = computed<{ state: State; points: string }[]>(() => {
    const pts = props.points;
    const width = stageWidth.value;
    const height = stageHeight.value;

    if (pts.length < 2 || width === 0) {
        return [];
    }

    const at = (point: Point) => `${point.x * width},${point.y * height}`;

    const result: { state: State; points: string }[] = [];
    let current: { state: State; coords: string[] } | null = null;

    const flush = () => {
        if (current && current.coords.length > 1) {
            result.push({
                state: current.state,
                points: current.coords.join(' '),
            });
        }
    };

    for (let i = 0; i < pts.length; i++) {
        const point = pts[i];

        if (point.gap || current === null) {
            flush();
            current = { state: point.state, coords: [at(point)] };
            continue;
        }

        const segmentState = pts[i - 1].state;

        if (segmentState !== current.state) {
            flush();
            current = { state: segmentState, coords: [at(pts[i - 1])] };
        }

        current.coords.push(at(point));
    }

    flush();

    return result;
});

const dotRadius = computed(() => 3 / scale.value);
const markerRadius = computed(() => 5 / scale.value);
const eventRadius = computed(() => 5.5 / scale.value);
const selectionRadius = computed(() => 9 / scale.value);
const hitRadius = computed(() => 11 / scale.value);

const firstPoint = computed(() => props.points.at(0) ?? null);
const lastPoint = computed(() => props.points.at(-1) ?? null);

const eventPoints = computed(() =>
    props.points.filter((p) => p.event !== null),
);

const eventLegend = computed<EventSlug[]>(() => {
    const present = new Set(eventPoints.value.map((p) => p.event as EventSlug));

    return EVENT_ORDER.filter((slug) => present.has(slug));
});

const {
    data: eventData,
    loading,
    error,
    load,
    reset: resetEvent,
} = useSessionEvent();

const selectedSequence = ref<number | null>(null);

const selectedPoint = computed(
    () =>
        props.points.find((p) => p.sequence === selectedSequence.value) ?? null,
);

const POPUP_CLEARANCE = 190;

const popupPlacement = computed(() => {
    const point = selectedPoint.value;

    if (!point) {
        return null;
    }

    const anchorX = translateX.value + point.x * stageWidth.value * scale.value;
    const anchorY =
        translateY.value + point.y * stageHeight.value * scale.value;
    const offset = eventRadius.value * scale.value + 8;
    const below = anchorY < POPUP_CLEARANCE;

    return {
        below,
        style: {
            left: `${anchorX}px`,
            top: `${below ? anchorY + offset : anchorY - offset}px`,
        },
    };
});

function selectEvent(point: Point): void {
    if (selectedSequence.value === point.sequence) {
        closePopup();

        return;
    }

    selectedSequence.value = point.sequence;
    void load(props.gameSessionId, point.sequence);
}

function closePopup(): void {
    selectedSequence.value = null;
    resetEvent();
}

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closePopup();
    }
});

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function zoomAt(pointerX: number, pointerY: number, factor: number): void {
    const next = clamp(scale.value * factor, MIN_SCALE, MAX_SCALE);
    const ratio = next / scale.value;

    translateX.value = pointerX - (pointerX - translateX.value) * ratio;
    translateY.value = pointerY - (pointerY - translateY.value) * ratio;
    scale.value = next;

    if (scale.value === MIN_SCALE) {
        translateX.value = 0;
        translateY.value = 0;
    }
}

function onWheel(event: WheelEvent): void {
    event.preventDefault();

    const rect = viewport.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    zoomAt(
        event.clientX - rect.left,
        event.clientY - rect.top,
        event.deltaY < 0 ? 1.15 : 1 / 1.15,
    );
}

function zoomButton(factor: number): void {
    zoomAt(
        viewportWidth.value / 2,
        viewport.value?.clientHeight ? viewport.value.clientHeight / 2 : 0,
        factor,
    );
}

let dragging = false;
let lastX = 0;
let lastY = 0;

function onPointerDown(event: PointerEvent): void {
    closePopup();
    dragging = true;
    lastX = event.clientX;
    lastY = event.clientY;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent): void {
    if (!dragging) {
        return;
    }

    translateX.value += event.clientX - lastX;
    translateY.value += event.clientY - lastY;
    lastX = event.clientX;
    lastY = event.clientY;
}

function onPointerUp(): void {
    dragging = false;
}

function reset(): void {
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;
}
</script>

<template>
    <div
        ref="viewport"
        class="wow-map-frame relative h-[72vh] cursor-grab touch-none overflow-hidden select-none active:cursor-grabbing"
        @wheel="onWheel"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointerleave="onPointerUp"
    >
        <div
            class="absolute top-0 left-0 origin-top-left"
            :style="{ transform }"
        >
            <div
                ref="stage"
                class="relative"
                :style="{ width: `${viewportWidth}px` }"
            >
                <img
                    :src="image"
                    alt=""
                    draggable="false"
                    class="block w-full select-none"
                />
            </div>
        </div>

        <svg
            v-if="stageWidth > 0 && viewportHeight > 0"
            class="pointer-events-none absolute inset-0 h-full w-full"
            :viewBox="`0 0 ${viewportWidth} ${viewportHeight}`"
        >
            <g
                :transform="`translate(${translateX} ${translateY}) scale(${scale})`"
            >
                <polyline
                    v-for="(segment, index) in segments"
                    :key="index"
                    :points="segment.points"
                    fill="none"
                    :stroke="STATE_COLORS[segment.state].line"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                    stroke-opacity="0.9"
                    vector-effect="non-scaling-stroke"
                />

                <circle
                    v-for="point in points"
                    :key="point.sequence"
                    :cx="point.x * stageWidth"
                    :cy="point.y * stageHeight"
                    :r="dotRadius"
                    :fill="STATE_COLORS[point.state].dot"
                    fill-opacity="0.95"
                />

                <circle
                    v-if="firstPoint"
                    :cx="firstPoint.x * stageWidth"
                    :cy="firstPoint.y * stageHeight"
                    :r="markerRadius"
                    fill="#34d399"
                    stroke="#0b0b0b"
                    stroke-width="1"
                    vector-effect="non-scaling-stroke"
                />

                <circle
                    v-if="lastPoint && lastPoint !== firstPoint"
                    :cx="lastPoint.x * stageWidth"
                    :cy="lastPoint.y * stageHeight"
                    :r="markerRadius"
                    fill="#f87171"
                    stroke="#0b0b0b"
                    stroke-width="1"
                    vector-effect="non-scaling-stroke"
                />

                <g class="pointer-events-auto">
                    <g
                        v-for="point in eventPoints"
                        :key="`event-${point.sequence}`"
                        class="cursor-pointer"
                        @pointerdown.stop
                        @click.stop="selectEvent(point)"
                    >
                        <circle
                            v-if="selectedSequence === point.sequence"
                            :cx="point.x * stageWidth"
                            :cy="point.y * stageHeight"
                            :r="selectionRadius"
                            fill="none"
                            :stroke="EVENT_COLORS[point.event!]"
                            stroke-width="1.5"
                            stroke-opacity="0.9"
                            vector-effect="non-scaling-stroke"
                        />
                        <circle
                            :cx="point.x * stageWidth"
                            :cy="point.y * stageHeight"
                            :r="eventRadius"
                            :fill="EVENT_COLORS[point.event!]"
                            :stroke="EVENT_OUTLINE"
                            stroke-width="1.5"
                            vector-effect="non-scaling-stroke"
                        />
                        <circle
                            :cx="point.x * stageWidth"
                            :cy="point.y * stageHeight"
                            :r="hitRadius"
                            fill="transparent"
                        />
                    </g>
                </g>
            </g>
        </svg>

        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                v-if="popupPlacement"
                class="pointer-events-auto absolute -translate-x-1/2"
                :class="popupPlacement.below ? '' : '-translate-y-full'"
                :style="popupPlacement.style"
                @pointerdown.stop
                @wheel.stop
            >
                <EventPopup
                    :event="eventData"
                    :loading="loading"
                    :error="error"
                    @close="closePopup"
                />
            </div>
        </div>

        <div class="absolute top-3 right-3 flex flex-col gap-1">
            <button
                type="button"
                class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                title="Zoom in"
                @click="zoomButton(1.3)"
            >
                <Plus class="size-4" />
            </button>
            <button
                type="button"
                class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                title="Zoom out"
                @click="zoomButton(1 / 1.3)"
            >
                <Minus class="size-4" />
            </button>
            <button
                type="button"
                class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                title="Reset view"
                @click="reset"
            >
                <RotateCcw class="size-4" />
            </button>
        </div>

        <div
            class="wow-frame absolute bottom-3 left-3 flex flex-col gap-1 bg-sidebar/90 px-3 py-2 text-xs text-muted-foreground"
        >
            <span
                v-for="item in LEGEND"
                :key="item.state"
                class="flex items-center gap-2"
            >
                <span
                    class="inline-block size-2.5 rounded-full"
                    :style="{ backgroundColor: STATE_COLORS[item.state].dot }"
                />
                {{ item.label }}
            </span>

            <template v-if="eventLegend.length">
                <span
                    class="mt-1 border-t border-border pt-1.5 text-[10px] tracking-wide uppercase"
                >
                    Events — click for details
                </span>
                <span
                    v-for="slug in eventLegend"
                    :key="slug"
                    class="flex items-center gap-2"
                >
                    <span
                        class="inline-flex size-3.5 shrink-0 items-center justify-center rounded-full"
                        :style="{
                            backgroundColor: EVENT_COLORS[slug],
                            boxShadow: `0 0 0 1px ${EVENT_OUTLINE}`,
                        }"
                    >
                        <component
                            :is="EVENT_ICONS[slug]"
                            class="size-2.5"
                            :style="{ color: EVENT_OUTLINE }"
                        />
                    </span>
                    {{ EVENT_LABELS[slug] }}
                </span>
            </template>
        </div>
    </div>
</template>
