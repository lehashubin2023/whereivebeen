export type IssueReportStatus = 'new' | 'resolved';

export interface IssueReportRow {
    id: number;
    message: string;
    status: IssueReportStatus;
    user_email: string | null;
    created_at: string | null;
}

export interface IssueReportsPaginator {
    data: IssueReportRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}
