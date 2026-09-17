<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import ChangePasswordDialog from '@/components/users/ChangePasswordDialog.vue';
import DeleteUserDialog from '@/components/users/DeleteUserDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index } from '@/routes/users';
import type { BreadcrumbItem, User } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return '—';
    
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
    if (!phone) return '—';
    
    // Remove caracteres não numéricos
    const numbers = phone.replace(/\D/g, '');
    
    // Formata baseado no tamanho
    if (numbers.length === 10) {
        // Telefone fixo: (XX) XXXX-XXXX
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    } else if (numbers.length === 11) {
        // Telefone celular: (XX) XXXXX-XXXX
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
    }
    
    return phone;
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
}

interface Props {
    users: Paginated<User>;
    filters: {
        search?: string | null;
        role?: string | null;
        active?: string | null;
        tenant_id?: string | null;
    };
    roles: string[];
    tenants: TenantOption[];
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
const tenantId = ref(props.filters.tenant_id ?? '');

const hasAnyFilter = computed(
    () =>
        !!search.value ||
        !!role.value ||
        !!tenantId.value ||
        active.value !== '',
);

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            role: role.value || undefined,
            active: active.value || undefined,
            tenant_id: tenantId.value || undefined,
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
    tenantId.value = '';
    applyFilters();
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
                        description="Gerencie os usuários cadastrados"
                        :icon="Users"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link :href="create()" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo usuário
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
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
                            v-model="tenantId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-56"
                            @change="applyFilters"
                        >
                            <option value="">Todas escolas</option>
                            <option
                                v-for="tenant in props.tenants"
                                :key="tenant.id"
                                :value="tenant.id"
                            >
                                {{ tenant.name }}
                            </option>
                        </select>

                        <select
                            v-model="role"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                            @change="applyFilters"
                        >
                            <option value="">Todos perfis</option>
                            <option v-for="r in props.roles" :key="r" :value="r">
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
                            v-if="hasAnyFilter"
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
                            class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                        >
                            <tr>
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Perfil</th>
                                <th class="px-4 py-3">Escola</th>
                                <th class="px-4 py-3">Telefone</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Último login</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="u in props.users.data"
                                :key="u.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ (u as any).nome_completo || u.name || '—' }}
                                    </div>
                                    <div
                                        v-if="u.cpf"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        CPF: {{ u.cpf }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ u.email || '—' }}</td>
                                <td class="px-4 py-3">
                                    <Badge 
                                        v-if="u.role"
                                        variant="secondary"
                                    >
                                        {{ u.role }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ (u as any).tenant_nome || (u as any).tenant?.nome || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatPhone((u as any).telefone || u.phone) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="(u as any).ativo !== false ? 'default' : 'destructive'"
                                    >
                                        {{ (u as any).ativo !== false ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
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
                                            <Link :href="edit({ user: u.id })">
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

                            <tr v-if="props.users.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum usuário encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.users.total }}</span>
                    </p>
                    <Pagination :links="props.users.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

