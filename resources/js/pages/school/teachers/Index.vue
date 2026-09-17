<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Eye, Plus, UserCheck } from 'lucide-vue-next';
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

interface TeacherDisciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Teacher {
    id: string;
    matricula: string;
    disciplinas?: TeacherDisciplina[] | null;
    especializacao?: string | null;
    ativo: boolean;
    nome_completo?: string;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
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
    teachers: Paginated<Teacher>;
    filters: {
        search?: string | null;
        active?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Professores',
        href: '/school/teachers',
    },
];

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active ?? '');

const hasAnyFilter = computed(
    () => !!search.value || active.value !== '',
);

function applyFilters() {
    router.get(
        '/school/teachers',
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Professores" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Professores"
                        description="Gerencie os professores cadastrados"
                        :icon="UserCheck"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/teachers/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo professor
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
                                placeholder="Buscar por matrícula, nome, CPF, e-mail ou telefone..."
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
                                <th class="px-4 py-3">Matrícula</th>
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">CPF</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Disciplinas</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="teacher in props.teachers.data"
                                :key="teacher.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ teacher.matricula }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ teacher.nome_completo || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatCPF(teacher.cpf) }}
                                </td>
                                <td class="px-4 py-3">{{ teacher.email || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="teacher.disciplinas && teacher.disciplinas.length > 0"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="disciplina in teacher.disciplinas.slice(0, 2)"
                                            :key="disciplina.id"
                                            class="rounded bg-primary/10 px-2 py-0.5 text-xs"
                                        >
                                            {{ disciplina.sigla || disciplina.nome }}
                                        </span>
                                        <span
                                            v-if="teacher.disciplinas.length > 2"
                                            class="text-xs text-muted-foreground"
                                            :title="teacher.disciplinas.map((d) => d.nome).join(', ')"
                                        >
                                            +{{ teacher.disciplinas.length - 2 }}
                                        </span>
                                    </div>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="teacher.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ teacher.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
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
                                            <Link :href="`/school/teachers/${teacher.id}`">
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
                                            <Link :href="`/school/teachers/${teacher.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.teachers.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum professor encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.teachers.total }}</span>
                    </p>
                    <Pagination :links="props.teachers.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

