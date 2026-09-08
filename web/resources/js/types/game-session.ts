export type RouteState = 'ground' | 'mounted' | 'flying';

export type EventSlug =
    | 'mount'
    | 'death'
    | 'resurrect'
    | 'levelup'
    | 'loot'
    | 'visit'
    | 'group'
    | 'quest'
    | 'taxi'
    | 'zone'
    | 'gather';

export interface RoutePoint {
    sequence: number;
    x: number;
    y: number;
    state: RouteState;
    gap: boolean;
    event: EventSlug | null;
}

export interface Zone {
    key: string;
    id: number;
    name: string;
    image_path: string;
    points_count: number;
    time: string;
    duration: number;
    points: RoutePoint[];
}

export interface SessionInfo {
    id: number;
    game_session_id: number;
    character: string;
    realm: string;
    session_start_at: string | null;
}

export interface EventDetail {
    label: string;
    value: string;
}

export interface SessionEvent {
    sequence: number;
    type: EventSlug;
    label: string;
    time: string | null;
    details: EventDetail[];
}
