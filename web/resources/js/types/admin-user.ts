export interface AdminUserRow {
    id: number;
    email: string;
    is_admin: boolean;
    sessions_count: number;
    email_verified_at: string | null;
    created_at: string | null;
}

export interface AdminUsersPaginator {
    data: AdminUserRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface AdminUserForm {
    id: number;
    email: string;
    is_admin: boolean;
}
