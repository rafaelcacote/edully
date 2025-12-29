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

export interface Tenant {
    id: string;
    name: string; // mapped from nome
    email: string;
    subdomain?: string | null; // mapped from subdominio
    cnpj?: string | null;
    phone?: string | null; // mapped from telefone
    address?: string | null; // mapped from endereco
    endereco_numero?: string | null;
    endereco_complemento?: string | null;
    endereco_bairro?: string | null;
    endereco_cep?: string | null;
    endereco_cidade?: string | null;
    endereco_estado?: string | null;
    endereco_pais?: string | null;
    logo_url?: string | null;
    plano_id?: string | null;
    is_active: boolean; // mapped from ativo
    trial_until?: string | null; // mapped from trial_ate
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
