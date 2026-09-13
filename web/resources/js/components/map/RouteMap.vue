<script setup lang="ts">
import {
    Maximize2,
    Minimize2,
    Minus,
    Pause,
    Play,
    Plus,
    RotateCcw,
} from '@lucide/vue';
import {
    onKeyStroke,
    useElementSize,
    useEventListener,
    useFullscreen,
    usePreferredReducedMotion,
    useRafFn,
} from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EventPopup from '@/components/map/EventPopup.vue';
import { useSessionEvent } from '@/composables/useSessionEvent';
import {
    EVENT_COLORS,
    EVENT_ICONS,
    EVENT_LABELS,
    EVENT_ORDER,
    EVENT_OUTLINE,
} from '@/lib/eventStyles';
import { t } from '@/lib/i18n';
import type { EventSlug, RoutePoint, RouteState } from '@/types';

type State = RouteState;
type Point = RoutePoint;

interface PlacedEvent {
    point: Point;
    x: number;
    y: number;
    anchorX: number;
    anchorY: number;
    fanned: boolean;
}

const FAN_SPREAD = 1.9;

/** Сколько длится проигрывание маршрута целиком. */
const PLAYBACK_MS = 18000;

const STATE_COLORS: Record<State, { line: string; dot: string }> = {
    ground: { line: '#b8c8db', dot: '#d3e0ee' },
    mounted: { line: EVENT_COLORS.mount, dot: EVENT_COLORS.mount },
    flying: { line: EVENT_COLORS.taxi, dot: EVENT_COLORS.taxi },
};

const TERMINAL_COLORS = {
    start: '#34d399',
    end: '#f87171',
    outline: '#0b0b0b',
};

const LEGEND: { state: State; label: string }[] = [
    { state: 'ground', label: t('On foot') },
    { state: 'mounted', label: t('Mounted') },
    { state: 'flying', label: t('Flying') },
];

const props = withDefaults(
    defineProps<{
        image: string;
        points: Point[];
        gameSessionId: number;
        heightClass?: string;
    }>(),
    {
        heightClass: 'h-[72vh]',
    },
);

const root = ref<HTMLElement | null>(null);
const viewport = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);

const { width: viewportWidth, height: viewportHeight } =
    useElementSize(viewport);
const { width: stageWidth, height: stageHeight } = useElementSize(stage);
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen(root);
const reducedMotion = usePreferredReducedMotion();

const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);

const MIN_SCALE = 1;
const MAX_SCALE = 16;

const transform = computed(
    () =>
        `translate(${translateX.value}px, ${translateY.value}px) scale(${scale.value})`,
);

const hiddenEvents = ref(new Set<EventSlug>());
const hiddenStates = ref(new Set<State>());

/** Доля проигранного маршрута по игровому времени, а не по числу точек. */
const progress = ref(1);
const playing = ref(false);

const startTime = computed(() => props.points.at(0)?.time ?? 0);
const endTime = computed(() => props.points.at(-1)?.time ?? 0);
const span = computed(() => Math.max(1, endTime.value - startTime.value));

const cursorTime = computed(
    () => startTime.value + progress.value * span.value,
);

const visibleCount = computed(() => {
    if (progress.value >= 1) {
        return props.points.length;
    }

    const limit = cursorTime.value;
    let low = 0;
    let high = props.points.length;

    while (low < high) {
        const middle = (low + high) >> 1;

        if (props.points[middle].time <= limit) {
            low = middle + 1;
        } else {
            high = middle;
        }
    }

    return Math.max(1, low);
});

const visiblePoints = computed(() => props.points.slice(0, visibleCount.value));

const scrubbing = computed(() => progress.value < 1);

const elapsedLabel = computed(() => {
    const seconds = Math.round((cursorTime.value - startTime.value) / 10);
    const total = Math.round(span.value / 10);

    return `${clock(seconds)} / ${clock(total)}`;
});

function clock(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    if (minutes < 60) {
        return `${minutes}:${String(rest).padStart(2, '0')}`;
    }

    return `${Math.floor(minutes / 60)}:${String(minutes % 60).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

const { pause: pauseRaf, resume: resumeRaf } = useRafFn(
    ({ delta }) => {
        progress.value = Math.min(1, progress.value + delta / PLAYBACK_MS);

        if (progress.value >= 1) {
            stop();
        }
    },
    { immediate: false },
);

function play(): void {
    if (props.points.length < 2) {
        return;
    }

    if (progress.value >= 1) {
        progress.value = 0;
    }

    playing.value = true;

    if (reducedMotion.value === 'reduce') {
        progress.value = 1;
        playing.value = false;

        return;
    }

    resumeRaf();
}

function stop(): void {
    playing.value = false;
    pauseRaf();
}

function togglePlay(): void {
    if (playing.value) {
        stop();

        return;
    }

    play();
}

function rewind(): void {
    stop();
    progress.value = 1;
}

function onScrub(event: Event): void {
    stop();
    progress.value = Number((event.target as HTMLInputElement).value) / 1000;
}

const segments = computed<{ state: State; points: string }[]>(() => {
    const pts = visiblePoints.value;
    const width = stageWidth.value;
    const height = stageHeight.value;

    if (pts.length < 2 || width === 0) {
        return [];
    }

    const at = (point: Point) => `${point.x * width},${point.y * height}`;

    const result: { state: State; points: string }[] = [];
    let current: { state: State; coords: string[] } | null = null;

    const flush = () => {
        if (
            current &&
            current.coords.length > 1 &&
            !hiddenStates.value.has(current.state)
        ) {
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

const routeDots = computed(() =>
    visiblePoints.value.filter((point) => !hiddenStates.value.has(point.state)),
);

const firstPoint = computed(() => props.points.at(0) ?? null);
const lastPoint = computed(() => visiblePoints.value.at(-1) ?? null);
const atEnd = computed(() => progress.value >= 1);

const eventPoints = computed(() =>
    visiblePoints.value.filter(
        (point) =>
            point.event !== null &&
            !hiddenEvents.value.has(point.event as EventSlug),
    ),
);

const placedEvents = computed<PlacedEvent[]>(() => {
    const width = stageWidth.value;
    const height = stageHeight.value;
    const groups = new Map<string, Point[]>();

    for (const point of eventPoints.value) {
        const key = `${point.x},${point.y}`;
        const group = groups.get(key);

        if (group) {
            group.push(point);
        } else {
            groups.set(key, [point]);
        }
    }

    const spread = eventRadius.value * FAN_SPREAD;
    const placed: PlacedEvent[] = [];

    for (const group of groups.values()) {
        const anchorX = group[0].x * width;
        const anchorY = group[0].y * height;

        if (group.length === 1) {
            placed.push({
                point: group[0],
                x: anchorX,
                y: anchorY,
                anchorX,
                anchorY,
                fanned: false,
            });

            continue;
        }

        const ordered = [...group].sort(
            (a, b) =>
                EVENT_ORDER.indexOf(a.event as EventSlug) -
                EVENT_ORDER.indexOf(b.event as EventSlug),
        );

        ordered.forEach((point, index) => {
            const angle = (Math.PI * 2 * index) / ordered.length - Math.PI / 2;

            placed.push({
                point,
                x: anchorX + Math.cos(angle) * spread,
                y: anchorY + Math.sin(angle) * spread,
                anchorX,
                anchorY,
                fanned: true,
            });
        });
    }

    return placed;
});

const fannedEvents = computed(() =>
    placedEvents.value.filter((placed) => placed.fanned),
);

const eventLegend = computed<EventSlug[]>(() => {
    const present = new Set(
        props.points
            .filter((point) => point.event !== null)
            .map((point) => point.event as EventSlug),
    );

    return EVENT_ORDER.filter((slug) => present.has(slug));
});

function toggleEvent(slug: EventSlug, solo: boolean): void {
    const next = new Set(hiddenEvents.value);

    if (solo) {
        const isolated =
            next.size === eventLegend.value.length - 1 && !next.has(slug);

        if (isolated) {
            next.clear();
        } else {
            next.clear();
            eventLegend.value.forEach((item) => {
                if (item !== slug) {
                    next.add(item);
                }
            });
        }
    } else if (next.has(slug)) {
        next.delete(slug);
    } else {
        next.add(slug);
    }

    hiddenEvents.value = next;
}

function toggleState(state: State): void {
    const next = new Set(hiddenStates.value);

    if (next.has(state)) {
        next.delete(state);
    } else {
        next.add(state);
    }

    hiddenStates.value = next;
}

const {
    data: eventData,
    loading,
    error,
    load,
    reset: resetEvent,
} = useSessionEvent();

const selectedSequence = ref<number | null>(null);
const hoveredSequence = ref<number | null>(null);

const selectedPlacement = computed(
    () =>
        placedEvents.value.find(
            (placed) => placed.point.sequence === selectedSequence.value,
        ) ?? null,
);

const hoveredPlacement = computed(
    () =>
        placedEvents.value.find(
            (placed) => placed.point.sequence === hoveredSequence.value,
        ) ?? null,
);

const POPUP_CLEARANCE = 190;

function screenPosition(placed: PlacedEvent): { x: number; y: number } {
    return {
        x: translateX.value + placed.x * scale.value,
        y: translateY.value + placed.y * scale.value,
    };
}

const popupPlacement = computed(() => {
    const placed = selectedPlacement.value;

    if (!placed) {
        return null;
    }

    const anchor = screenPosition(placed);
    const offset = eventRadius.value * scale.value + 8;
    const below = anchor.y < POPUP_CLEARANCE;

    return {
        below,
        style: {
            left: `${anchor.x}px`,
            top: `${below ? anchor.y + offset : anchor.y - offset}px`,
        },
    };
});

const hoverTooltip = computed(() => {
    const placed = hoveredPlacement.value;

    if (!placed || placed.point.sequence === selectedSequence.value) {
        return null;
    }

    const anchor = screenPosition(placed);
    const slug = placed.point.event as EventSlug;

    return {
        label: t(EVENT_LABELS[slug]),
        time: clock(Math.round((placed.point.time - startTime.value) / 10)),
        style: {
            left: `${anchor.x}px`,
            top: `${anchor.y - eventRadius.value * scale.value - 10}px`,
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

function stepEvent(direction: 1 | -1): void {
    const ordered = props.points.filter(
        (point) =>
            point.event !== null &&
            !hiddenEvents.value.has(point.event as EventSlug),
    );

    if (ordered.length === 0) {
        return;
    }

    const current = ordered.findIndex(
        (point) => point.sequence === selectedSequence.value,
    );

    const next =
        current === -1
            ? direction === 1
                ? 0
                : ordered.length - 1
            : (current + direction + ordered.length) % ordered.length;

    const point = ordered[next];

    if (point.time > cursorTime.value) {
        stop();
        progress.value = 1;
    }

    selectedSequence.value = point.sequence;
    void load(props.gameSessionId, point.sequence);
}

watch(
    () => props.points,
    () => {
        closePopup();
        stop();
        progress.value = 1;
        hiddenEvents.value = new Set();
        hiddenStates.value = new Set();
    },
);

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closePopup();
    }
});

function typingInAField(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;

    return (
        !!target &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable)
    );
}

function bindKey(key: string, handler: () => void): void {
    onKeyStroke(key, (event) => {
        if (typingInAField(event)) {
            return;
        }

        event.preventDefault();
        handler();
    });
}

bindKey(' ', togglePlay);
bindKey('ArrowRight', () => stepEvent(1));
bindKey('ArrowLeft', () => stepEvent(-1));
bindKey('f', reset);
bindKey('+', () => zoomButton(1.3));
bindKey('=', () => zoomButton(1.3));
bindKey('-', () => zoomButton(1 / 1.3));

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function clampTranslate(): void {
    const scaledWidth = stageWidth.value * scale.value;
    const scaledHeight = stageHeight.value * scale.value;

    translateX.value = clamp(
        translateX.value,
        Math.min(0, viewportWidth.value - scaledWidth),
        0,
    );
    translateY.value = clamp(
        translateY.value,
        Math.min(0, viewportHeight.value - scaledHeight),
        0,
    );
}

watch(
    [scale, stageWidth, stageHeight, viewportWidth, viewportHeight],
    clampTranslate,
);

function zoomAt(pointerX: number, pointerY: number, factor: number): void {
    const next = clamp(scale.value * factor, MIN_SCALE, MAX_SCALE);
    const ratio = next / scale.value;

    translateX.value = pointerX - (pointerX - translateX.value) * ratio;
    translateY.value = pointerY - (pointerY - translateY.value) * ratio;
    scale.value = next;

    clampTranslate();
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
    zoomAt(viewportWidth.value / 2, viewportHeight.value / 2, factor);
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

    clampTranslate();
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
    <div ref="root" class="flex flex-col gap-3 bg-background">
        <div class="wow-map-frame">
            <div
                ref="viewport"
                class="relative cursor-grab touch-none overflow-hidden select-none active:cursor-grabbing"
                :class="isFullscreen ? 'h-svh' : heightClass"
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
                            v-for="point in routeDots"
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
                            :fill="TERMINAL_COLORS.start"
                            :stroke="TERMINAL_COLORS.outline"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />

                        <circle
                            v-if="lastPoint && lastPoint !== firstPoint"
                            :cx="lastPoint.x * stageWidth"
                            :cy="lastPoint.y * stageHeight"
                            :r="markerRadius"
                            :fill="
                                atEnd
                                    ? TERMINAL_COLORS.end
                                    : STATE_COLORS[lastPoint.state].dot
                            "
                            :stroke="TERMINAL_COLORS.outline"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />

                        <line
                            v-for="placed in fannedEvents"
                            :key="`leader-${placed.point.sequence}`"
                            :x1="placed.anchorX"
                            :y1="placed.anchorY"
                            :x2="placed.x"
                            :y2="placed.y"
                            :stroke="EVENT_OUTLINE"
                            stroke-width="1"
                            stroke-opacity="0.55"
                            vector-effect="non-scaling-stroke"
                        />

                        <g class="pointer-events-auto">
                            <g
                                v-for="placed in placedEvents"
                                :key="`event-${placed.point.sequence}`"
                                class="cursor-pointer"
                                @pointerdown.stop
                                @click.stop="selectEvent(placed.point)"
                                @mouseenter="
                                    hoveredSequence = placed.point.sequence
                                "
                                @mouseleave="hoveredSequence = null"
                            >
                                <circle
                                    v-if="
                                        selectedSequence ===
                                        placed.point.sequence
                                    "
                                    :cx="placed.x"
                                    :cy="placed.y"
                                    :r="selectionRadius"
                                    fill="none"
                                    :stroke="EVENT_COLORS[placed.point.event!]"
                                    stroke-width="1.5"
                                    stroke-opacity="0.9"
                                    vector-effect="non-scaling-stroke"
                                />
                                <circle
                                    :cx="placed.x"
                                    :cy="placed.y"
                                    :r="eventRadius"
                                    :fill="EVENT_COLORS[placed.point.event!]"
                                    :stroke="EVENT_OUTLINE"
                                    stroke-width="1.5"
                                    vector-effect="non-scaling-stroke"
                                />
                                <circle
                                    :cx="placed.x"
                                    :cy="placed.y"
                                    :r="hitRadius"
                                    fill="transparent"
                                />
                            </g>
                        </g>
                    </g>
                </svg>

                <div
                    class="pointer-events-none absolute inset-0 overflow-hidden"
                >
                    <div
                        v-if="hoverTooltip"
                        class="absolute -translate-x-1/2 -translate-y-full rounded border border-border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground shadow-sm"
                        :style="hoverTooltip.style"
                    >
                        {{ hoverTooltip.label }}
                        <span class="font-mono text-muted-foreground">
                            {{ hoverTooltip.time }}
                        </span>
                    </div>

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

                    <div
                        class="pointer-events-auto absolute top-5 right-5 flex flex-col gap-1"
                    >
                        <button
                            type="button"
                            class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                            :title="t('Zoom in')"
                            @pointerdown.stop
                            @click="zoomButton(1.3)"
                        >
                            <Plus class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                            :title="t('Zoom out')"
                            @pointerdown.stop
                            @click="zoomButton(1 / 1.3)"
                        >
                            <Minus class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                            :title="t('Reset view')"
                            @pointerdown.stop
                            @click="reset"
                        >
                            <RotateCcw class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="wow-frame text-gold flex size-8 items-center justify-center bg-sidebar"
                            :title="t('Fullscreen')"
                            @pointerdown.stop
                            @click="toggleFullscreen"
                        >
                            <Minimize2 v-if="isFullscreen" class="size-4" />
                            <Maximize2 v-else class="size-4" />
                        </button>
                    </div>

                    <div
                        v-if="points.length > 1"
                        class="pointer-events-auto absolute right-5 bottom-5 left-5 flex items-center gap-3 rounded-md border border-border bg-popover/90 px-3 py-2 backdrop-blur"
                        @pointerdown.stop
                        @wheel.stop
                    >
                        <button
                            type="button"
                            class="text-gold flex size-7 shrink-0 items-center justify-center"
                            :title="playing ? t('Pause') : t('Play route')"
                            @click="togglePlay"
                        >
                            <Pause v-if="playing" class="size-4" />
                            <Play v-else class="size-4" />
                        </button>

                        <input
                            type="range"
                            min="0"
                            max="1000"
                            :value="Math.round(progress * 1000)"
                            class="h-1 flex-1 cursor-pointer accent-[var(--primary)]"
                            :aria-label="t('Route timeline')"
                            @input="onScrub"
                        />

                        <span
                            class="shrink-0 font-mono text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ elapsedLabel }}
                        </span>

                        <button
                            v-if="scrubbing"
                            type="button"
                            class="shrink-0 font-mono text-[11px] text-muted-foreground underline-offset-4 hover:underline"
                            @click="rewind"
                        >
                            {{ t('Whole route') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="wow-panel flex flex-col gap-2 px-4 py-3 text-xs text-muted-foreground"
        >
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <span v-if="firstPoint" class="flex items-center gap-2">
                    <span
                        class="inline-block size-2.5 rounded-full"
                        :style="{
                            backgroundColor: TERMINAL_COLORS.start,
                            boxShadow: `0 0 0 1px ${TERMINAL_COLORS.outline}`,
                        }"
                    />
                    {{ t('Start') }}
                </span>

                <span
                    v-if="lastPoint && lastPoint !== firstPoint"
                    class="flex items-center gap-2"
                >
                    <span
                        class="inline-block size-2.5 rounded-full"
                        :style="{
                            backgroundColor: TERMINAL_COLORS.end,
                            boxShadow: `0 0 0 1px ${TERMINAL_COLORS.outline}`,
                        }"
                    />
                    {{ t('End') }}
                </span>

                <button
                    v-for="item in LEGEND"
                    :key="item.state"
                    type="button"
                    class="flex items-center gap-2 transition-opacity"
                    :class="
                        hiddenStates.has(item.state)
                            ? 'opacity-40'
                            : 'hover:text-foreground'
                    "
                    :title="t('Show or hide')"
                    @click="toggleState(item.state)"
                >
                    <span
                        class="inline-block size-2.5 rounded-full"
                        :style="{
                            backgroundColor: STATE_COLORS[item.state].dot,
                        }"
                    />
                    {{ item.label }}
                </button>
            </div>

            <template v-if="eventLegend.length">
                <hr class="wow-divider" />

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                    <button
                        v-for="slug in eventLegend"
                        :key="slug"
                        type="button"
                        class="flex items-center gap-2 transition-opacity"
                        :class="
                            hiddenEvents.has(slug)
                                ? 'opacity-40'
                                : 'hover:text-foreground'
                        "
                        :title="
                            t('Click to hide, shift-click to show only this')
                        "
                        @click="toggleEvent(slug, $event.shiftKey)"
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
                        {{ t(EVENT_LABELS[slug]) }}
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
