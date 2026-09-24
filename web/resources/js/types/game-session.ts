import type { Paginator } from './ui';

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
    time: number;
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
    image_path: string | null;
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

export type ImportStatus = 'new' | 'in_process' | 'completed' | 'failed';

export type ImportOutcome = 'created' | 'replaced';

export interface ImportWarnings {
    unknown_maps?: Record<string, number>;
    new_maps?: number[];
}

export interface ImportRow {
    id: number;
    status: ImportStatus;
    outcome: ImportOutcome | null;
    points_total: number;
    points_done: number;
    execution_time: number;
    error_code: string | null;
    error_context: Record<string, unknown> | null;
    warnings: ImportWarnings | null;
    import_batch_id: number | null;
    game_session_id: number | null;
    created_at: string | null;
}

export interface ImportSkip {
    session_id: string;
    character: string | null;
    points: number;
    reason: string;
}

export type ImportBatchState =
    'queued' | 'parsing' | 'importing' | 'completed' | 'failed';

export interface ImportBatchRow {
    id: number;
    filename: string;
    file_size: number;
    state: ImportBatchState;
    settled: boolean;
    sessions_found: number;
    sessions_queued: number;
    sessions_skipped: number;
    sessions_finished: number;
    sessions_failed: number;
    skipped: ImportSkip[] | null;
    error_code: string | null;
    error_context: Record<string, unknown> | null;
    created_at: string | null;
}

export type ImportsPaginator = Paginator<ImportRow>;

export type ImportBatchesPaginator = Paginator<ImportBatchRow>;

export type ImportTab = 'sessions' | 'files';
