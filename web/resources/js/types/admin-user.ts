import type { Paginator } from './ui';

export interface AdminUserRow {
    id: number;
    email: string;
    is_admin: boolean;
    is_main_admin: boolean;
    sessions_count: number;
    email_verified_at: string | null;
    created_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
}

export type AdminUsersPaginator = Paginator<AdminUserRow>;

export interface AdminUserForm {
    id: number;
    email: string;
    is_admin: boolean;
    is_main_admin: boolean;
}
