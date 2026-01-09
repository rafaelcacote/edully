<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import DeletePermissionDialog from '@/components/permissions/DeletePermissionDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index } from '@/routes/permissions';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Plus, KeyRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
}

interface PermissionRow {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
}

interface Props {
    permissions: Paginated<PermissionRow>;
    filters: {
        search?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Permissões',
        href: index().url,
    },
];

const search = ref(props.filters.search ?? '');

const hasAnyFilter = computed(() => !!search.value);

// Função para obter o nome do grupo a partir do nome da permissão
function getGroupName(permissionName: string): string {
    const parts = permissionName.split('.');
    if (parts.length > 1) {
        return parts[0];
    }
    return 'Outros';
}

// Função para obter o nome amigável do grupo
function getGroupLabel(groupName: string): string {
    const labels: Record<string, string> = {
        escola: 'Escola',
        escolas: 'Escolas',
        usuarios: 'Usuários',
        roles: 'Roles',
        permissoes: 'Permissões',
        assinaturas: 'Assinaturas',
        planos: 'Planos',
        logs: 'Logs do Sistema',
    };
    return labels[groupName] || groupName.charAt(0).toUpperCase() + groupName.slice(1);
}

// Função para obter o nome do subgrupo (segunda parte do nome da permissão)
function getSubgroupName(permissionName: string): string {
    const parts = permissionName.split('.');
    if (parts.length > 2) {
        return parts[1];
    }
    if (parts.length === 2) {
        return parts[1];
    }
    return 'Geral';
}

// Função para obter o nome amigável do subgrupo
function getSubgroupLabel(subgroupName: string): string {
    const labels: Record<string, string> = {
        alunos: 'Alunos',
        disciplinas: 'Disciplinas',
        exercicios: 'Exercícios',
        perfil: 'Perfil',
        professores: 'Professores',
        provas: 'Provas',
        responsaveis: 'Responsáveis',
        turmas: 'Turmas',
    };
    return labels[subgroupName] || subgroupName.charAt(0).toUpperCase() + subgroupName.slice(1);
}

// Tipo para grupos com subgrupos
type GroupedPermissions = Record<string, PermissionRow[] | Record<string, PermissionRow[]>>;

// Agrupar permissões por módulo (com subgrupos para escola)
const groupedPermissions = computed(() => {
    const term = search.value.trim().toLowerCase();
    let filtered = props.permissions.data;

    if (term) {
        filtered = props.permissions.data.filter((p) =>
            p.name.toLowerCase().includes(term),
        );
    }

    // Agrupar por módulo
    const groups: Record<string, PermissionRow[]> = {};

    filtered.forEach((permission) => {
        const groupName = getGroupName(permission.name);
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(permission);
    });

    // Criar estrutura com subgrupos para escola
    const sortedGroups: GroupedPermissions = {};
    
    Object.keys(groups)
        .sort()
        .forEach((groupName) => {
            const permissions = groups[groupName].sort((a, b) =>
                a.name.localeCompare(b.name),
            );

            // Se for o grupo "escola", criar subgrupos
            if (groupName === 'escola' || groupName === 'escolas') {
                const subgroups: Record<string, PermissionRow[]> = {};
                
                permissions.forEach((permission) => {
                    const subgroupName = getSubgroupName(permission.name);
                    if (!subgroups[subgroupName]) {
                        subgroups[subgroupName] = [];
                    }
                    subgroups[subgroupName].push(permission);
                });

                // Ordenar subgrupos
                const sortedSubgroups: Record<string, PermissionRow[]> = {};
                Object.keys(subgroups)
                    .sort()
                    .forEach((subgroupName) => {
                        sortedSubgroups[subgroupName] = subgroups[subgroupName].sort((a, b) =>
                            a.name.localeCompare(b.name),
                        );
                    });

                sortedGroups[groupName] = sortedSubgroups;
            } else {
                // Para outros grupos, manter como array simples
                sortedGroups[groupName] = permissions;
            }
        });

    return sortedGroups;
});

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
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
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Permissões" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Permissões"
                        description="Gerencie as permissões do sistema"
                        :icon="KeyRound"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link :href="create()" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Nova permissão
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
                                placeholder="Buscar por nome..."
                                @keyup.enter="applyFilters"
                            />
                        </div>
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

            <div v-if="Object.keys(groupedPermissions).length > 0" class="space-y-6">
                <template
                    v-for="(groupData, groupName) in groupedPermissions"
                    :key="groupName"
                >
                    <!-- Grupo com subgrupos (escola) -->
                    <div
                        v-if="
                            groupName === 'escola' ||
                            groupName === 'escolas'
                        "
                        class="rounded-xl border bg-card shadow-sm"
                    >
                        <div class="border-b bg-neutral-50 px-4 py-3 dark:bg-neutral-900/40">
                            <h3 class="text-sm font-semibold text-foreground">
                                {{ getGroupLabel(groupName) }}
                            </h3>
                        </div>

                        <div class="space-y-6 p-4">
                            <div
                                v-for="(permissions, subgroupName) in groupData as Record<string, PermissionRow[]>"
                                :key="subgroupName"
                                class="space-y-3"
                            >
                                <h4 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ getSubgroupLabel(subgroupName) }}
                                </h4>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-sm">
                                        <thead
                                            class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                                        >
                                            <tr>
                                                <th class="px-4 py-3">Nome</th>
                                                <th class="px-4 py-3">Guard</th>
                                                <th class="px-4 py-3 text-right">Ações</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr
                                                v-for="p in permissions"
                                                :key="p.id"
                                                class="border-b last:border-0"
                                            >
                                                <td class="px-4 py-3">
                                                    <div class="font-medium">{{ p.name }}</div>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <Badge variant="secondary">{{
                                                        p.guard_name
                                                    }}</Badge>
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
                                                                :href="
                                                                    edit({
                                                                        permission: p.id,
                                                                    })
                                                                "
                                                            >
                                                                <Edit
                                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                                />
                                                            </Link>
                                                        </Button>

                                                        <DeletePermissionDialog
                                                            :permission="p"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grupos sem subgrupos (outros grupos) -->
                    <div
                        v-else
                        class="rounded-xl border bg-card shadow-sm"
                    >
                        <div class="border-b bg-neutral-50 px-4 py-3 dark:bg-neutral-900/40">
                            <h3 class="text-sm font-semibold text-foreground">
                                {{ getGroupLabel(groupName) }}
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead
                                    class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                                >
                                    <tr>
                                        <th class="px-4 py-3">Nome</th>
                                        <th class="px-4 py-3">Guard</th>
                                        <th class="px-4 py-3 text-right">Ações</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="p in groupData as PermissionRow[]"
                                        :key="p.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ p.name }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <Badge variant="secondary">{{
                                                p.guard_name
                                            }}</Badge>
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
                                                        :href="
                                                            edit({
                                                                permission: p.id,
                                                            })
                                                        "
                                                    >
                                                        <Edit
                                                            class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                        />
                                                    </Link>
                                                </Button>

                                                <DeletePermissionDialog
                                                    :permission="p"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <div
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total:
                        <span class="font-medium">{{
                            props.permissions.total
                        }}</span>
                    </p>
                    <Pagination :links="props.permissions.links" />
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border bg-card p-10 text-center shadow-sm"
            >
                <p class="text-sm text-muted-foreground">
                    Nenhuma permissão encontrada.
                </p>
            </div>
        </div>
    </AppLayout>
</template>


