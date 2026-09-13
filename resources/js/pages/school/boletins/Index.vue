<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { FileSpreadsheet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: number | string | null;
}

interface Aluno {
    id: string;
    nome: string;
}

interface DisciplinaBoletim {
    id: string;
    nome: string;
    sigla?: string | null;
    bimestres: {
        1: number | null;
        2: number | null;
        3: number | null;
        4: number | null;
    };
    media: number | null;
}

interface Boletim {
    aluno: Aluno;
    turma: Turma;
    disciplinas: DisciplinaBoletim[];
    media_geral: number | null;
}

interface Props {
    turmas: Turma[];
    alunos: Aluno[];
    filters: {
        turma_id?: string | null;
        aluno_id?: string | null;
    };
    boletim: Boletim | null;
}

const props = withDefaults(defineProps<Props>(), {
    turmas: () => [],
    alunos: () => [],
    boletim: null,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Boletins',
        href: '/school/boletins',
    },
];

const turmaId = ref(props.filters.turma_id ?? '');
const alunoId = ref(props.filters.aluno_id ?? '');

watch(turmaId, (value, oldValue) => {
    if (oldValue && value !== oldValue) {
        alunoId.value = '';
    }
});

function applyFilters() {
    router.get(
        '/school/boletins',
        {
            turma_id: turmaId.value || undefined,
            aluno_id: alunoId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function formatNota(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
        return '—';
    }

    return Number(valor).toFixed(1);
}

const temBoletim = computed(() => !!props.boletim);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Boletins" />

        <div class="space-y-6">
            <div class="mt-2">
                <Heading
                    title="Boletins"
                    description="Consulte o boletim bimestral do aluno"
                    :icon="FileSpreadsheet"
                />
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="turma_id">Turma</Label>
                        <select
                            id="turma_id"
                            v-model="turmaId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            @change="applyFilters"
                        >
                            <option value="">Selecione a turma</option>
                            <option
                                v-for="turma in props.turmas"
                                :key="turma.id"
                                :value="turma.id"
                            >
                                {{ turma.nome }}
                                <template v-if="turma.ano_letivo">
                                    ({{ turma.ano_letivo }})
                                </template>
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="aluno_id">Aluno</Label>
                        <select
                            id="aluno_id"
                            v-model="alunoId"
                            :disabled="!turmaId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                            @change="applyFilters"
                        >
                            <option value="">
                                {{ turmaId ? 'Selecione o aluno' : 'Selecione a turma primeiro' }}
                            </option>
                            <option
                                v-for="aluno in props.alunos"
                                :key="aluno.id"
                                :value="aluno.id"
                            >
                                {{ aluno.nome }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <Button
                            type="button"
                            class="w-full"
                            :disabled="!turmaId || !alunoId"
                            @click="applyFilters"
                        >
                            Ver boletim
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="temBoletim && props.boletim"
                class="space-y-4"
            >
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold">
                                {{ props.boletim.aluno.nome }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ props.boletim.turma.nome }}
                                <template v-if="props.boletim.turma.serie || props.boletim.turma.turma_letra">
                                    · {{ [props.boletim.turma.serie, props.boletim.turma.turma_letra].filter(Boolean).join(' ') }}
                                </template>
                                <template v-if="props.boletim.turma.ano_letivo">
                                    · Ano letivo {{ props.boletim.turma.ano_letivo }}
                                </template>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            Média geral:
                            {{ formatNota(props.boletim.media_geral) }}
                        </Badge>
                    </div>
                </div>

                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">Disciplina</th>
                                    <th class="px-4 py-3 text-center">1º</th>
                                    <th class="px-4 py-3 text-center">2º</th>
                                    <th class="px-4 py-3 text-center">3º</th>
                                    <th class="px-4 py-3 text-center">4º</th>
                                    <th class="px-4 py-3 text-center">Média</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="disciplina in props.boletim.disciplinas"
                                    :key="disciplina.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ disciplina.nome }}</div>
                                        <div
                                            v-if="disciplina.sigla"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ disciplina.sigla }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center tabular-nums">
                                        {{ formatNota(disciplina.bimestres[1]) }}
                                    </td>
                                    <td class="px-4 py-3 text-center tabular-nums">
                                        {{ formatNota(disciplina.bimestres[2]) }}
                                    </td>
                                    <td class="px-4 py-3 text-center tabular-nums">
                                        {{ formatNota(disciplina.bimestres[3]) }}
                                    </td>
                                    <td class="px-4 py-3 text-center tabular-nums">
                                        {{ formatNota(disciplina.bimestres[4]) }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold tabular-nums">
                                        {{ formatNota(disciplina.media) }}
                                    </td>
                                </tr>

                                <tr v-if="props.boletim.disciplinas.length === 0">
                                    <td
                                        colspan="6"
                                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        Esta turma ainda não tem grade curricular.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground shadow-sm"
            >
                Selecione a turma e o aluno para visualizar o boletim.
            </div>
        </div>
    </AppLayout>
</template>
