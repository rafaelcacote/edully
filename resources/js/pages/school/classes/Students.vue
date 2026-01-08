<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, GraduationCap, User } from 'lucide-vue-next';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Student {
    id: string;
    nome: string;
    nome_social?: string | null;
    foto_url?: string | null;
    data_nascimento?: string | null;
    ativo: boolean;
    matricula_id?: string;
    data_matricula?: string | null;
}

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: string | null;
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
    turma: Turma;
    students: Paginated<Student>;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Turmas',
        href: '/school/classes',
    },
    {
        title: props.turma.nome,
        href: `/school/classes/${props.turma.id}/students`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Alunos da turma: ${props.turma.nome}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="`Alunos - ${props.turma.nome}`"
                        :description="`Lista de alunos matriculados na turma`"
                        :icon="GraduationCap"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/classes" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold">{{ props.turma.nome }}</h3>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="props.turma.serie || props.turma.turma_letra">
                            {{ [props.turma.serie, props.turma.turma_letra].filter(Boolean).join(' - ') }}
                        </template>
                        <template v-if="props.turma.ano_letivo">
                            • Ano letivo: {{ props.turma.ano_letivo }}
                        </template>
                    </p>
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
                                <th class="px-4 py-3">Data de nascimento</th>
                                <th class="px-4 py-3">Data de matrícula</th>
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
                                    {{ student.data_nascimento ? new Date(student.data_nascimento).toLocaleDateString('pt-BR') : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ student.data_matricula ? new Date(student.data_matricula).toLocaleDateString('pt-BR') : '—' }}
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
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.students.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum aluno matriculado nesta turma.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.students.total }}</span> aluno(s)
                    </p>
                    <Pagination :links="props.students.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>


