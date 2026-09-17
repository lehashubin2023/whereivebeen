import type { Paginator } from './ui';

export type IssueReportStatus = 'new' | 'resolved';

export interface IssueReportRow {
    id: number;
    message: string;
    status: IssueReportStatus;
    user_email: string | null;
    created_at: string | null;
}

export type IssueReportsPaginator = Paginator<IssueReportRow>;
