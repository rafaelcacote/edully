<script setup lang="ts">
import DeleteTestDialog from '@/components/tests/DeleteTestDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardCheck, Edit, Eye, Plus } from 'lucide-vue-next';
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
    ano_letivo?: number | string | null;
}

interface Test {
    id: string;
    titulo: string;
    disciplina: string | null;
    data_prova: string;
    horario?: string | null;
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

interface Disciplina {
    id: string;
    nome: string;
}

interface Props {
    tests: Paginated<Test>;
    turmas: Turma[];
    disciplinas: Disciplina[];
    filters: {
        search?: string | null;
        turma_id?: string | null;
        disciplina_id?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Provas',
        href: '/school/tests',
    },
];

const search = ref(props.filters.search ?? '');
const turmaId = ref(props.filters.turma_id ?? '');
const disciplinaId = ref(props.filters.disciplina_id ?? '');

const hasAnyFilter = computed(
    () => !!search.value || turmaId.value !== '' || disciplinaId.value !== '',
);

function applyFilters() {
    router.get(
        '/school/tests',
        {
            search: search.value || undefined,
            turma_id: turmaId.value || undefined,
            disciplina_id: disciplinaId.value || undefined,
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
    turmaId.value = '';
    disciplinaId.value = '';
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Provas" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Provas"
                        description="Gerencie as provas cadastradas"
                        :icon="ClipboardCheck"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/tests/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Nova prova
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
                                placeholder="Buscar por título, disciplina ou descrição..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="turmaId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                            @change="applyFilters"
                        >
                            <option value="">Todas as turmas</option>
                            <option
                                v-for="turma in turmas"
                                :key="turma.id"
                                :value="turma.id"
                            >
                                {{ turma.nome }}
                            </option>
                        </select>

                        <select
                            v-model="disciplinaId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                            @change="applyFilters"
                        >
                            <option value="">Todas as disciplinas</option>
                            <option
                                v-for="disc in disciplinas"
                                :key="disc.id"
                                :value="disc.id"
                            >
                                {{ disc.nome }}
                            </option>
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
                                <th class="px-4 py-3">Título</th>
                                <th class="px-4 py-3">Disciplina</th>
                                <th class="px-4 py-3">Turma</th>
                                <th class="px-4 py-3">Data da Prova</th>
                                <th class="px-4 py-3">Horário</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="test in props.tests.data"
                                :key="test.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ test.titulo }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ test.disciplina || '—' }}</td>
                                <td class="px-4 py-3">
                                    <template v-if="test.turma">
                                        {{ test.turma.nome }}<span v-if="test.turma.turma_letra"> {{ test.turma.turma_letra }}</span>
                                    </template>
                                    <template v-else>
                                        —
                                    </template>
                                </td>
                                <td class="px-4 py-3">{{ test.data_prova }}</td>
                                <td class="px-4 py-3">{{ test.horario || '—' }}</td>
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
                                            <Link :href="`/school/tests/${test.id}`">
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
                                            <Link :href="`/school/tests/${test.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                        <DeleteTestDialog :test="test" />
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.tests.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhuma prova encontrada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.tests.total }}</span>
                    </p>
                    <Pagination :links="props.tests.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

