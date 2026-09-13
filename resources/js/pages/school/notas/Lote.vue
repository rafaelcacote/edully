<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Turma {
    id: string;
    nome: string;
    ano_letivo?: number | string | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
    professor_id?: string | null;
}

interface Professor {
    id: string;
    nome_completo: string;
}

interface AlunoNota {
    id: string;
    nome: string;
    nota?: number | string | null;
}

interface Props {
    turmas: Turma[];
    professores: Professor[];
    grade: Record<string, Disciplina[]>;
    filters: {
        turma_id?: string | null;
        disciplina_id?: string | null;
        bimestre?: string | null;
    };
    turma?: Turma | null;
    disciplina?: Disciplina | null;
    alunos: AlunoNota[];
    professor_padrao_id?: string | null;
    ano_letivo?: number | string | null;
}

const props = withDefaults(defineProps<Props>(), {
    turmas: () => [],
    professores: () => [],
    grade: () => ({}),
    alunos: () => [],
    turma: null,
    disciplina: null,
    professor_padrao_id: null,
    ano_letivo: null,
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notas',
        href: '/school/notas',
    },
    {
        title: 'Lançar em lote',
        href: '/school/notas/lote',
    },
];

const turmaId = ref(props.filters.turma_id ?? '');
const disciplinaId = ref(props.filters.disciplina_id ?? '');
const bimestre = ref(props.filters.bimestre?.toString() ?? '');

const disciplinasDaTurma = computed(() => {
    if (!turmaId.value) {
        return [] as Disciplina[];
    }

    return props.grade?.[turmaId.value] || [];
});

const podeCarregarLista = computed(
    () => !!turmaId.value && !!disciplinaId.value && !!bimestre.value,
);

const form = useForm({
    turma_id: props.filters.turma_id ?? '',
    disciplina_id: props.filters.disciplina_id ?? '',
    professor_id: props.professor_padrao_id ?? '',
    bimestre: props.filters.bimestre?.toString() ?? '',
    ano_letivo: props.ano_letivo?.toString() ?? new Date().getFullYear().toString(),
    notas: props.alunos.map((aluno) => ({
        aluno_id: aluno.id,
        nota: aluno.nota !== null && aluno.nota !== undefined ? String(aluno.nota) : '',
    })),
});

watch(
    () => [props.alunos, props.professor_padrao_id, props.ano_letivo, props.filters],
    () => {
        form.turma_id = props.filters.turma_id ?? '';
        form.disciplina_id = props.filters.disciplina_id ?? '';
        form.bimestre = props.filters.bimestre?.toString() ?? '';
        form.ano_letivo = props.ano_letivo?.toString() ?? new Date().getFullYear().toString();
        form.professor_id = props.professor_padrao_id ?? form.professor_id;
        form.notas = props.alunos.map((aluno) => ({
            aluno_id: aluno.id,
            nota: aluno.nota !== null && aluno.nota !== undefined ? String(aluno.nota) : '',
        }));
    },
    { deep: true },
);

watch(turmaId, (value, oldValue) => {
    if (oldValue && value !== oldValue) {
        disciplinaId.value = '';
    }
});

function carregarLista() {
    router.get(
        '/school/notas/lote',
        {
            turma_id: turmaId.value || undefined,
            disciplina_id: disciplinaId.value || undefined,
            bimestre: bimestre.value || undefined,
        },
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        },
    );
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            bimestre: Number(data.bimestre),
            ano_letivo: Number(data.ano_letivo),
            notas: data.notas.map((item) => ({
                aluno_id: item.aluno_id,
                nota: item.nota === '' ? null : item.nota,
            })),
        }))
        .put('/school/notas/lote', {
            preserveScroll: true,
        });
}

const preenchidas = computed(
    () => form.notas.filter((item) => item.nota !== '' && item.nota !== null).length,
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Lançar notas em lote" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Lançar notas em lote"
                        description="Preencha as notas da turma por disciplina e bimestre"
                        :icon="ClipboardList"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/notas" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="grid gap-2">
                        <Label for="filtro_turma">Turma</Label>
                        <select
                            id="filtro_turma"
                            v-model="turmaId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Selecione a turma</option>
                            <option
                                v-for="turmaItem in props.turmas"
                                :key="turmaItem.id"
                                :value="turmaItem.id"
                            >
                                {{ turmaItem.nome }}
                                <template v-if="turmaItem.ano_letivo">
                                    ({{ turmaItem.ano_letivo }})
                                </template>
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="filtro_disciplina">Disciplina</Label>
                        <select
                            id="filtro_disciplina"
                            v-model="disciplinaId"
                            :disabled="!turmaId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                        >
                            <option value="">
                                {{ turmaId ? 'Selecione a disciplina' : 'Selecione a turma primeiro' }}
                            </option>
                            <option
                                v-for="disciplinaItem in disciplinasDaTurma"
                                :key="disciplinaItem.id"
                                :value="disciplinaItem.id"
                            >
                                {{ disciplinaItem.nome }}{{ disciplinaItem.sigla ? ` (${disciplinaItem.sigla})` : '' }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="filtro_bimestre">Bimestre</Label>
                        <select
                            id="filtro_bimestre"
                            v-model="bimestre"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Selecione</option>
                            <option value="1">1º Bimestre</option>
                            <option value="2">2º Bimestre</option>
                            <option value="3">3º Bimestre</option>
                            <option value="4">4º Bimestre</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <Button
                            type="button"
                            class="w-full"
                            :disabled="!podeCarregarLista"
                            @click="carregarLista"
                        >
                            Carregar alunos
                        </Button>
                    </div>
                </div>
            </div>

            <form
                v-if="props.alunos.length > 0"
                class="space-y-4"
                @submit.prevent="submit"
            >
                <input type="hidden" name="turma_id" :value="form.turma_id" />
                <input type="hidden" name="disciplina_id" :value="form.disciplina_id" />
                <input type="hidden" name="bimestre" :value="form.bimestre" />
                <input type="hidden" name="ano_letivo" :value="form.ano_letivo" />

                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-semibold">
                            {{ props.turma?.nome }}
                        </h3>
                        <Badge variant="secondary">
                            {{ props.disciplina?.nome }}
                        </Badge>
                        <Badge variant="outline">
                            {{ form.bimestre }}º bimestre
                        </Badge>
                        <Badge variant="outline">
                            {{ preenchidas }}/{{ form.notas.length }} preenchidas
                        </Badge>
                    </div>

                    <div class="grid gap-2 sm:max-w-md">
                        <Label for="professor_id">Professor responsável</Label>
                        <select
                            id="professor_id"
                            v-model="form.professor_id"
                            required
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Selecione o professor</option>
                            <option
                                v-for="professor in props.professores"
                                :key="professor.id"
                                :value="professor.id"
                            >
                                {{ professor.nome_completo }}
                            </option>
                        </select>
                        <InputError :message="form.errors.professor_id" />
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
                                    <th class="px-4 py-3 w-40">Nota (0 a 10)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in form.notas"
                                    :key="item.aluno_id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        {{ props.alunos[index]?.nome }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="item.nota"
                                            type="number"
                                            min="0"
                                            max="10"
                                            step="0.1"
                                            placeholder="—"
                                            class="flex h-10 w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-sm shadow-sm outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                                        />
                                        <InputError :message="form.errors[`notas.${index}.nota`]" />
                                        <InputError :message="form.errors[`notas.${index}.aluno_id`]" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <InputError :message="form.errors.turma_id" />
                <InputError :message="form.errors.disciplina_id" />
                <InputError :message="form.errors.bimestre" />
                <InputError :message="form.errors.notas" />

                <div class="flex items-center justify-end gap-2">
                    <Button type="submit" :disabled="form.processing" class="flex items-center gap-2">
                        <Save class="h-4 w-4" />
                        Salvar notas
                    </Button>
                </div>
            </form>

            <div
                v-else-if="podeCarregarLista && props.filters.turma_id"
                class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground shadow-sm"
            >
                Nenhum aluno matriculado nesta turma.
            </div>

            <div
                v-else
                class="rounded-xl border bg-card p-10 text-center text-sm text-muted-foreground shadow-sm"
            >
                Selecione turma, disciplina e bimestre para carregar a lista de alunos.
            </div>
        </div>
    </AppLayout>
</template>
