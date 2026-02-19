<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Eye, Plus, Trash2, ClipboardList } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Aluno {
    id: string;
    nome: string;
}

interface Professor {
    id: string;
    usuario?: {
        nome_completo: string;
    } | null;
}

interface Turma {
    id: string;
    nome: string;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Nota {
    id: string;
    aluno: Aluno | null;
    professor: Professor | null;
    turma: Turma | null;
    disciplina: string;
    disciplina_id: string | null;
    trimestre: number;
    nota: number;
    frequencia: number | null;
    comportamento: string | null;
    observacoes: string | null;
    ano_letivo: number;
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
    notas: Paginated<Nota>;
    filters: {
        search?: string | null;
        aluno_id?: string | null;
        professor_id?: string | null;
        turma_id?: string | null;
        disciplina_id?: string | null;
        trimestre?: string | null;
        ano_letivo?: string | null;
    };
    alunos: Aluno[];
    professores: Array<{ id: string; nome_completo: string }>;
    turmas: Turma[];
    disciplinas: Disciplina[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notas',
        href: '/school/notas',
    },
];

const search = ref(props.filters.search ?? '');
const alunoId = ref(props.filters.aluno_id ?? '');
const professorId = ref(props.filters.professor_id ?? '');
const turmaId = ref(props.filters.turma_id ?? '');
const disciplinaId = ref(props.filters.disciplina_id ?? '');
const trimestre = ref(props.filters.trimestre ?? '');
const anoLetivo = ref(props.filters.ano_letivo ?? '');

const hasAnyFilter = computed(
    () => !!search.value || !!alunoId.value || !!professorId.value || !!turmaId.value || !!disciplinaId.value || !!trimestre.value || !!anoLetivo.value,
);

function applyFilters() {
    router.get(
        '/school/notas',
        {
            search: search.value || undefined,
            aluno_id: alunoId.value || undefined,
            professor_id: professorId.value || undefined,
            turma_id: turmaId.value || undefined,
            disciplina_id: disciplinaId.value || undefined,
            trimestre: trimestre.value || undefined,
            ano_letivo: anoLetivo.value || undefined,
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
    alunoId.value = '';
    professorId.value = '';
    turmaId.value = '';
    disciplinaId.value = '';
    trimestre.value = '';
    anoLetivo.value = '';
    applyFilters();
}

function deleteNota(notaId: string) {
    if (confirm('Tem certeza que deseja excluir esta nota?')) {
        router.delete(`/school/notas/${notaId}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Notas" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Notas"
                        description="Gerencie as notas dos alunos"
                        :icon="ClipboardList"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/notas/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Nova nota
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-1 flex-col gap-3">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                            <div class="flex-1">
                                <Input
                                    v-model="search"
                                    placeholder="Buscar por disciplina, observações ou aluno..."
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <select
                                v-model="alunoId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                                @change="applyFilters"
                            >
                                <option value="">Todos os alunos</option>
                                <option
                                    v-for="aluno in props.alunos"
                                    :key="aluno.id"
                                    :value="aluno.id"
                                >
                                    {{ aluno.nome }}
                                </option>
                            </select>

                            <select
                                v-model="professorId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                                @change="applyFilters"
                            >
                                <option value="">Todos os professores</option>
                                <option
                                    v-for="professor in props.professores"
                                    :key="professor.id"
                                    :value="professor.id"
                                >
                                    {{ professor.nome_completo }}
                                </option>
                            </select>

                            <select
                                v-model="turmaId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                                @change="applyFilters"
                            >
                                <option value="">Todas as turmas</option>
                                <option
                                    v-for="turma in props.turmas"
                                    :key="turma.id"
                                    :value="turma.id"
                                >
                                    {{ turma.nome }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                            <select
                                v-model="disciplinaId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                                @change="applyFilters"
                            >
                                <option value="">Todas as disciplinas</option>
                                <option
                                    v-for="disciplina in props.disciplinas"
                                    :key="disciplina.id"
                                    :value="disciplina.id"
                                >
                                    {{ disciplina.nome }}{{ disciplina.sigla ? ` (${disciplina.sigla})` : '' }}
                                </option>
                            </select>

                            <select
                                v-model="trimestre"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                                @change="applyFilters"
                            >
                                <option value="">Todos os trimestres</option>
                                <option value="1">1º Trimestre</option>
                                <option value="2">2º Trimestre</option>
                                <option value="3">3º Trimestre</option>
                            </select>

                            <input
                                v-model="anoLetivo"
                                type="number"
                                placeholder="Ano letivo"
                                min="2000"
                                max="2100"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-32"
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

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                        >
                            <tr>
                                <th class="px-4 py-3">Aluno</th>
                                <th class="px-4 py-3">Disciplina</th>
                                <th class="px-4 py-3">Professor</th>
                                <th class="px-4 py-3">Turma</th>
                                <th class="px-4 py-3">Trimestre</th>
                                <th class="px-4 py-3">Nota</th>
                                <th class="px-4 py-3">Frequência</th>
                                <th class="px-4 py-3">Ano Letivo</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="nota in props.notas.data"
                                :key="nota.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ nota.aluno?.nome || '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ nota.disciplina || '—' }}</td>
                                <td class="px-4 py-3">
                                    {{ nota.professor?.usuario?.nome_completo || '—' }}
                                </td>
                                <td class="px-4 py-3">{{ nota.turma?.nome || '—' }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">
                                        {{ nota.trimestre }}º Trimestre
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold">{{ nota.nota.toFixed(1) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ nota.frequencia !== null ? `${nota.frequencia}%` : '—' }}
                                </td>
                                <td class="px-4 py-3">{{ nota.ano_letivo }}</td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/notas/${nota.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                            @click="deleteNota(nota.id)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-red-500 dark:text-red-400"
                                            />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.notas.data.length === 0">
                                <td
                                    colspan="9"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhuma nota encontrada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.notas.total }}</span>
                    </p>
                    <Pagination :links="props.notas.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
