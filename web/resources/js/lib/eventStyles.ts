import {
    ArrowUp,
    Coins,
    Feather,
    MapPin,
    Pickaxe,
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
    'gather',
    'zone',
];

export const EVENT_COLORS: Record<EventSlug, string> = {
    levelup: '#4ade80',
    quest: '#facc15',
    loot: '#fb923c',
    death: '#ef4444',
    resurrect: '#e8eaed',
    group: '#d8b48a',
    visit: '#9ca3af',
    mount: '#38bdf8',
    taxi: '#f472b6',
    gather: '#34d399',
    zone: '#a78bfa',
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
    gather: 'Gather',
    zone: 'Zone',
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
    gather: Pickaxe,
    zone: MapPin,
};

export const EVENT_OUTLINE = '#1a1207';
