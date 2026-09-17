import type { Paginator } from './ui';

export interface AdminUserRow {
    id: number;
    email: string;
    is_admin: boolean;
    sessions_count: number;
    email_verified_at: string | null;
    created_at: string | null;
}

export type AdminUsersPaginator = Paginator<AdminUserRow>;

export interface AdminUserForm {
    id: number;
    email: string;
    is_admin: boolean;
}
