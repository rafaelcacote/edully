<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, GraduationCap, Plus, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: string | null;
}

interface Student {
    id: string;
    nome: string;
    nome_social?: string | null;
    foto_url?: string | null;
    data_nascimento?: string | null;
    ativo: boolean;
    turma?: Turma | null;
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
    students: Paginated<Student>;
    filters: {
        search?: string | null;
        active?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Alunos',
        href: '/school/students',
    },
];

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active ?? '');

const hasAnyFilter = computed(
    () => !!search.value || active.value !== '',
);

function formatDate(dateString: string | null | undefined): string {
    if (!dateString) return '—';
    
    try {
        // Parse a data manualmente para evitar problemas de timezone
        // Assume formato YYYY-MM-DD do banco de dados
        const [year, month, day] = dateString.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        
        return new Intl.DateTimeFormat('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(date);
    } catch {
        return dateString;
    }
}

function applyFilters() {
    router.get(
        '/school/students',
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
        <Head title="Alunos" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Alunos"
                        description="Gerencie os alunos cadastrados"
                        :icon="GraduationCap"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/students/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo aluno
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
                                placeholder="Buscar por nome ou nome social..."
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
                                <th class="px-4 py-3">Nome social</th>
                                <th class="px-4 py-3">Turma</th>
                                <th class="px-4 py-3">Data de nascimento</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="student in props.students.data"
                                :key="student.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ student.nome }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ student.nome_social || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="student.turma">
                                        {{ student.turma.nome }}
                                        <template v-if="student.turma.serie || student.turma.turma_letra">
                                            ({{ [student.turma.serie, student.turma.turma_letra].filter(Boolean).join(' - ') }})
                                        </template>
                                        <template v-if="student.turma.ano_letivo">
                                            - {{ student.turma.ano_letivo }}
                                        </template>
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatDate(student.data_nascimento) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="student.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ student.ativo ? 'Ativo' : 'Inativo' }}
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
                                            <Link :href="`/school/students/${student.id}`">
                                                <User
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
                                            <Link :href="`/school/students/${student.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.students.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum aluno encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.students.total }}</span>
                    </p>
                    <Pagination :links="props.students.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

