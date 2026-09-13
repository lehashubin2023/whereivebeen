export interface JourneyDay {
    date: string;
    seconds: number;
    sessions: number;
}

export interface JourneyZone {
    name: string;
    seconds: number;
    points: number;
}

export interface JourneyLevel {
    level: number;
    date: string;
}

export interface Journey {
    activity: JourneyDay[];
    zones: JourneyZone[];
    levels: JourneyLevel[];
    deadliest: { name: string; deaths: number } | null;
}

export interface StatisticOverview {
    label: string;
    value: string;
}

export interface StatisticTable {
    title: string;
    columns: string[];
    rows: string[][];
    rows_total: number;
}

export interface StatisticGroup {
    slug: string;
    label: string;
    total: number;
    tables: StatisticTable[];
}
