import {
    ArrowUp,
    Coins,
    Feather,
    HeartPulse,
    Rabbit,
    ScrollText,
    Skull,
    Store,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { EventSlug } from '@/types';

export const EVENT_ORDER: EventSlug[] = [
    'levelup',
    'quest',
    'loot',
    'death',
    'resurrect',
    'group',
    'visit',
    'mount',
    'taxi',
];

export const EVENT_COLORS: Record<EventSlug, string> = {
    levelup: '#4ade80',
    quest: '#facc15',
    loot: '#fb923c',
    death: '#ef4444',
    resurrect: '#e8eaed',
    group: '#38bdf8',
    visit: '#9ca3af',
    mount: '#d8b48a',
    taxi: '#f472b6',
};

export const EVENT_LABELS: Record<EventSlug, string> = {
    levelup: 'Level up',
    quest: 'Quest',
    loot: 'Loot',
    death: 'Death',
    resurrect: 'Resurrect',
    group: 'Group',
    visit: 'Visit',
    mount: 'Mount',
    taxi: 'Taxi',
};

export const EVENT_ICONS: Record<EventSlug, Component> = {
    levelup: ArrowUp,
    quest: ScrollText,
    loot: Coins,
    death: Skull,
    resurrect: HeartPulse,
    group: Users,
    visit: Store,
    mount: Rabbit,
    taxi: Feather,
};

export const EVENT_OUTLINE = '#1a1207';
