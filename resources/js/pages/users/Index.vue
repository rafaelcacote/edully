<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ChangePasswordDialog from '@/components/users/ChangePasswordDialog.vue';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index } from '@/routes/users';
import type { BreadcrumbItem, User } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronRight,
    Edit,
    Plus,
    School,
    Search,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) {
        return '—';
    }

    try {
        const date = new Date(dateString);

        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    } catch {
        return dateString;
    }
}

function formatPhone(phone: string | null | undefined): string {
    if (!phone) {
        return '—';
    }

    const numbers = phone.replace(/\D/g, '');

    if (numbers.length === 10) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    }

    if (numbers.length === 11) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
    }

    return phone;
}

function schoolInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return 'E';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}

function usersLabel(count: number): string {
    return count === 1 ? '1 usuário' : `${count} usuários`;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface TenantOption {
    id: string;
    name: string;
    subdomain?: string | null;
    city?: string | null;
    logo_url?: string | null;
    is_active?: boolean;
    users_count?: number;
}

interface Props {
    users: Paginated<User> | null;
    filters: {
        search?: string | null;
        role?: string | null;
        active?: string | null;
        tenant_id?: string | null;
    };
    roles: string[];
    tenants: TenantOption[];
    selectedTenant: TenantOption | null;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Usuários',
        href: index().url,
    },
];

const search = ref(props.filters.search ?? '');
const role = ref(props.filters.role ?? '');
const active = ref(props.filters.active ?? '');
const schoolSearch = ref('');
const brokenLogos = ref<Record<string, boolean>>({});

function showLogo(tenant: TenantOption): boolean {
    return Boolean(tenant.logo_url) && !brokenLogos.value[tenant.id];
}

function markLogoBroken(tenantId: string): void {
    brokenLogos.value = {
        ...brokenLogos.value,
        [tenantId]: true,
    };
}

const hasListFilter = computed(
    () => !!search.value || !!role.value || active.value !== '',
);

const filteredTenants = computed(() => {
    const term = schoolSearch.value.trim().toLowerCase();

    if (!term) {
        return props.tenants;
    }

    return props.tenants.filter((tenant) => {
        const haystack = [tenant.name, tenant.subdomain, tenant.city]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    });
});

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            role: role.value || undefined,
            active: active.value || undefined,
            tenant_id: props.selectedTenant?.id,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearFilters() {
    search.value = '';
    role.value = '';
    active.value = '';
    applyFilters();
}

function selectTenant(tenantId: string) {
    router.get(index().url, { tenant_id: tenantId });
}

function changeTenant() {
    router.get(index().url);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Usuários" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Usuários"
                        :description="
                            props.selectedTenant
                                ? 'Usuários vinculados à escola selecionada'
                                : 'Escolha uma escola para ver somente os usuários dela'
                        "
                        :icon="Users"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link
                            :href="
                                props.selectedTenant
                                    ? `${create().url}?tenant_id=${props.selectedTenant.id}`
                                    : create()
                            "
                            class="flex items-center gap-2"
                        >
                            <Plus class="h-4 w-4" />
                            Novo usuário
                        </Link>
                    </Button>
                </div>
            </div>

            <template v-if="!props.selectedTenant">
                <div class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <School class="h-4 w-4 text-primary" />
                                <h3 class="text-sm font-semibold">Escolas</h3>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ props.tenants.length }}
                                {{
                                    props.tenants.length === 1
                                        ? 'escola cadastrada'
                                        : 'escolas cadastradas'
                                }}
                            </p>
                        </div>

                        <div class="relative w-full sm:max-w-sm">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="schoolSearch"
                                class="pl-9"
                                placeholder="Buscar escola por nome, cidade ou subdomínio"
                            />
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredTenants.length > 0"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <button
                        v-for="tenant in filteredTenants"
                        :key="tenant.id"
                        type="button"
                        class="group flex items-center gap-4 rounded-xl border bg-card p-4 text-left shadow-sm transition hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="selectTenant(tenant.id)"
                    >
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl text-sm font-semibold"
                            :class="
                                showLogo(tenant)
                                    ? 'border bg-white dark:bg-neutral-900'
                                    : 'bg-primary/10 text-primary'
                            "
                        >
                            <img
                                v-if="showLogo(tenant)"
                                :src="tenant.logo_url ?? undefined"
                                :alt="`Logo de ${tenant.name}`"
                                class="h-full w-full object-contain"
                                @error="markLogoBroken(tenant.id)"
                            />
                            <span v-else>{{
                                schoolInitials(tenant.name)
                            }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate font-medium">
                                    {{ tenant.name }}
                                </p>
                                <Badge
                                    :variant="
                                        tenant.is_active === false
                                            ? 'secondary'
                                            : 'default'
                                    "
                                    class="shrink-0"
                                >
                                    {{
                                        tenant.is_active === false
                                            ? 'Inativa'
                                            : 'Ativa'
                                    }}
                                </Badge>
                            </div>
                            <p
                                class="mt-1 truncate text-sm text-muted-foreground"
                            >
                                {{
                                    [tenant.city, tenant.subdomain]
                                        .filter(Boolean)
                                        .join(' · ') || 'Sem cidade informada'
                                }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ usersLabel(tenant.users_count ?? 0) }}
                            </p>
                        </div>

                        <ChevronRight
                            class="h-4 w-4 shrink-0 text-muted-foreground transition group-hover:text-primary"
                        />
                    </button>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed bg-card px-6 py-16 text-center shadow-sm"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-muted"
                    >
                        <School class="h-5 w-5 text-muted-foreground" />
                    </div>
                    <p class="mt-4 font-medium">
                        {{
                            props.tenants.length === 0
                                ? 'Nenhuma escola cadastrada'
                                : 'Nenhuma escola encontrada'
                        }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            props.tenants.length === 0
                                ? 'Cadastre uma escola para começar a gerenciar usuários.'
                                : 'Tente outro nome, cidade ou subdomínio.'
                        }}
                    </p>
                </div>
            </template>

            <template v-else>
                <div
                    class="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-center gap-4">
                        <div
                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl text-sm font-semibold"
                            :class="
                                showLogo(props.selectedTenant)
                                    ? 'border bg-white dark:bg-neutral-900'
                                    : 'bg-primary/10 text-primary'
                            "
                        >
                            <img
                                v-if="showLogo(props.selectedTenant)"
                                :src="
                                    props.selectedTenant.logo_url ?? undefined
                                "
                                :alt="`Logo de ${props.selectedTenant.name}`"
                                class="h-full w-full object-contain"
                                @error="markLogoBroken(props.selectedTenant.id)"
                            />
                            <span v-else>{{
                                schoolInitials(props.selectedTenant.name)
                            }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-muted-foreground">
                                Escola selecionada
                            </p>
                            <p class="truncate text-base font-semibold">
                                {{ props.selectedTenant.name }}
                            </p>
                            <p class="truncate text-sm text-muted-foreground">
                                {{
                                    usersLabel(
                                        props.selectedTenant.users_count ?? 0,
                                    )
                                }}
                                <template
                                    v-if="
                                        props.selectedTenant.city ||
                                        props.selectedTenant.subdomain
                                    "
                                >
                                    ·
                                    {{
                                        [
                                            props.selectedTenant.city,
                                            props.selectedTenant.subdomain,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </template>
                            </p>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        class="shrink-0"
                        @click="changeTenant"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Trocar escola
                    </Button>
                </div>

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                            <div class="flex-1">
                                <Input
                                    v-model="search"
                                    placeholder="Buscar por nome, CPF, e-mail ou telefone..."
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <select
                                v-model="role"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                                @change="applyFilters"
                            >
                                <option value="">Todos perfis</option>
                                <option
                                    v-for="r in props.roles"
                                    :key="r"
                                    :value="r"
                                >
                                    {{ r }}
                                </option>
                            </select>

                            <select
                                v-model="active"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                                @change="applyFilters"
                            >
                                <option value="">Todos status</option>
                                <option value="true">Ativos</option>
                                <option value="false">Inativos</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button variant="secondary" @click="applyFilters">
                                Filtrar
                            </Button>
                            <Button
                                v-if="hasListFilter"
                                variant="ghost"
                                @click="clearFilters"
                            >
                                Limpar
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b bg-neutral-50 text-xs tracking-wide text-neutral-500 uppercase dark:bg-neutral-900/40 dark:text-neutral-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Nome</th>
                                    <th class="px-4 py-3">E-mail</th>
                                    <th class="px-4 py-3">Perfil</th>
                                    <th class="px-4 py-3">Telefone</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Último login</th>
                                    <th class="px-4 py-3 text-right">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="u in props.users?.data ?? []"
                                    :key="u.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-medium">
                                            {{
                                                (u as any).nome_completo ||
                                                u.name ||
                                                '—'
                                            }}
                                        </div>
                                        <div
                                            v-if="u.cpf"
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            CPF: {{ u.cpf }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ u.email || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            v-if="u.role"
                                            variant="secondary"
                                        >
                                            {{ u.role }}
                                        </Badge>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{
                                            formatPhone(
                                                (u as any).telefone || u.phone,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            :variant="
                                                (u as any).ativo !== false
                                                    ? 'default'
                                                    : 'destructive'
                                            "
                                        >
                                            {{
                                                (u as any).ativo !== false
                                                    ? 'Ativo'
                                                    : 'Inativo'
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        {{ formatDate(u.last_login_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="flex items-center justify-end gap-2"
                                        >
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                            >
                                                <Link
                                                    :href="edit({ user: u.id })"
                                                >
                                                    <Edit
                                                        class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                    />
                                                </Link>
                                            </Button>
                                            <ChangePasswordDialog :user="u" />
                                            <DeleteUserDialog :user="u" />
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-if="(props.users?.data.length ?? 0) === 0"
                                >
                                    <td
                                        colspan="7"
                                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        Nenhum usuário encontrado nesta escola.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-sm text-muted-foreground">
                            Total:
                            <span class="font-medium">{{
                                props.users?.total ?? 0
                            }}</span>
                        </p>
                        <Pagination :links="props.users?.links ?? []" />
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
