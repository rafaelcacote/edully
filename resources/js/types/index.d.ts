import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    toast?: {
        type: 'success' | 'error' | 'info';
        message: string;
        title?: string;
    } | null;
};

export interface User {
    id: string;
    name: string; // mapped from full_name
    email: string;
    full_name: string;
    cpf?: string | null;
    role: string;
    phone?: string | null;
    avatar_url?: string | null;
    is_active: boolean;
    last_login_at?: string | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
