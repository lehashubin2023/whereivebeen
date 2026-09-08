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
