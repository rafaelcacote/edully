<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Eye, Plus, Users, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

function formatPhone(phone: string | null | undefined): string {
    if (!phone) return '—';
    const numbers = phone.replace(/\D/g, '');
    if (numbers.length === 10) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    } else if (numbers.length === 11) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
    }
    return phone;
}

function formatCPF(cpf: string | null | undefined): string {
    if (!cpf) return '—';
    const numbers = cpf.replace(/\D/g, '');
    if (numbers.length === 11) {
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9, 11)}`;
    }
    return cpf;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface LinkedStudent {
    id: string;
    nome: string;
    nome_social?: string | null;
}

interface Parent {
    id: string;
    nome_completo: string;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
    parentesco?: string | null;
    ativo: boolean;
    students?: LinkedStudent[];
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    parents: Paginated<Parent>;
    filters: {
        search?: string | null;
        active?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Responsáveis',
        href: '/school/parents',
    },
];

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active ?? '');

const hasAnyFilter = computed(
    () => !!search.value || active.value !== '',
);

function applyFilters() {
    router.get(
        '/school/parents',
        {
            search: search.value || undefined,
            active: active.value || undefined,
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
    active.value = '';
    applyFilters();
}

function sortedStudents(students: LinkedStudent[] | undefined): LinkedStudent[] {
    return [...(students ?? [])].sort((left, right) =>
        left.nome.localeCompare(right.nome, 'pt-BR'),
    );
}

function firstName(nome: string): string {
    return nome.trim().split(/\s+/)[0] || nome;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Responsáveis" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Responsáveis"
                        description="Gerencie os responsáveis cadastrados"
                        :icon="Users"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/parents/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo responsável
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
                                <th class="px-4 py-3">Alunos</th>
                                <th class="px-4 py-3">CPF</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Telefone</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="parent in props.parents.data"
                                :key="parent.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <div class="font-medium">
                                        {{ parent.nome_completo }}
                                    </div>
                                    <div
                                        v-if="parent.parentesco"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ parent.parentesco }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <p
                                        v-if="parent.students?.length"
                                        class="text-sm"
                                    >
                                        <template
                                            v-for="(student, index) in sortedStudents(parent.students)"
                                            :key="student.id"
                                        >
                                            <span v-if="index > 0">, </span>
                                            <Link
                                                :href="`/school/students/${student.id}`"
                                                :title="student.nome"
                                                class="underline-offset-2 hover:underline"
                                            >
                                                {{ firstName(student.nome) }}
                                            </Link>
                                        </template>
                                    </p>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    {{ formatCPF(parent.cpf) }}
                                </td>
                                <td class="px-4 py-3 align-top">{{ parent.email || '—' }}</td>
                                <td class="px-4 py-3 align-top">
                                    {{ formatPhone(parent.telefone) }}
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <Badge
                                        :variant="parent.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ parent.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="secondary"
                                            class="px-3"
                                        >
                                            <Link :href="`/school/parents/${parent.id}`" class="flex items-center gap-1">
                                                <UsersRound class="h-4 w-4" />
                                                Alunos
                                            </Link>
                                        </Button>
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/parents/${parent.id}`">
                                                <Eye
                                                    class="h-4 w-4 text-blue-500 dark:text-blue-400"
                                                />
                                            </Link>
                                        </Button>
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/parents/${parent.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.parents.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum responsável encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.parents.total }}</span>
                    </p>
                    <Pagination :links="props.parents.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

