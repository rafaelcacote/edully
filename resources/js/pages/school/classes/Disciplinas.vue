<script setup lang="ts">
import Can from '@/components/Can.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, BookMarked, Save } from 'lucide-vue-next';
import { computed } from 'vue';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: number | string | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Professor {
    id: string;
    nome_completo: string;
}

interface Vinculo {
    disciplina_id: string;
    professor_id?: string | null;
}

interface Props {
    turma: Turma;
    disciplinas: Disciplina[];
    professores: Professor[];
    vinculadas: Vinculo[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Turmas',
        href: '/school/classes',
    },
    {
        title: props.turma.nome,
        href: `/school/classes/${props.turma.id}`,
    },
    {
        title: 'Disciplinas',
        href: `/school/classes/${props.turma.id}/disciplinas`,
    },
];

const form = useForm({
    disciplinas: props.vinculadas.map((item) => ({
        disciplina_id: String(item.disciplina_id),
        professor_id: item.professor_id ? String(item.professor_id) : '',
    })),
});

const selectedIds = computed(() =>
    form.disciplinas.map((item) => String(item.disciplina_id)),
);

const selectedCount = computed(() => form.disciplinas.length);

function isSelected(disciplinaId: string): boolean {
    return selectedIds.value.includes(String(disciplinaId));
}

function professorFor(disciplinaId: string): string {
    const item = form.disciplinas.find(
        (row) => String(row.disciplina_id) === String(disciplinaId),
    );

    return item?.professor_id ? String(item.professor_id) : '';
}

function toggleDisciplina(disciplinaId: string, checked: boolean | 'indeterminate') {
    const id = String(disciplinaId);
    const isChecked = checked === true;

    if (isChecked) {
        if (!isSelected(id)) {
            form.disciplinas = [
                ...form.disciplinas,
                {
                    disciplina_id: id,
                    professor_id: '',
                },
            ];
        }

        return;
    }

    form.disciplinas = form.disciplinas.filter(
        (item) => String(item.disciplina_id) !== id,
    );
}

function setProfessor(disciplinaId: string, professorId: string) {
    const id = String(disciplinaId);
    const item = form.disciplinas.find(
        (row) => String(row.disciplina_id) === id,
    );

    if (!item) {
        return;
    }

    item.professor_id = professorId;
}

function submit() {
    form
        .transform((data) => ({
            disciplinas: data.disciplinas.map((item) => ({
                disciplina_id: item.disciplina_id,
                professor_id: item.professor_id || null,
            })),
        }))
        .put(`/school/classes/${props.turma.id}/disciplinas`, {
            preserveScroll: true,
        });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Disciplinas da turma: ${props.turma.nome}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="`Disciplinas - ${props.turma.nome}`"
                        description="Monte a grade curricular desta turma para o boletim"
                        :icon="BookMarked"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link
                        :href="`/school/classes/${props.turma.id}`"
                        class="flex items-center gap-2 px-4 py-2"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-1 flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold">{{ props.turma.nome }}</h3>
                    <Badge variant="secondary">
                        {{ selectedCount }}
                        {{ selectedCount === 1 ? 'disciplina' : 'disciplinas' }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    <template v-if="props.turma.serie || props.turma.turma_letra">
                        {{ [props.turma.serie, props.turma.turma_letra].filter(Boolean).join(' - ') }}
                    </template>
                    <template v-if="props.turma.ano_letivo">
                        • Ano letivo: {{ props.turma.ano_letivo }}
                    </template>
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="rounded-xl border bg-card shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                            >
                                <tr>
                                    <th class="px-4 py-3 w-12"></th>
                                    <th class="px-4 py-3">Disciplina</th>
                                    <th class="px-4 py-3">Professor da matéria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="disciplina in props.disciplinas"
                                    :key="disciplina.id"
                                    class="border-b last:border-0"
                                >
                                    <td class="px-4 py-3">
                                        <Checkbox
                                            :model-value="isSelected(disciplina.id)"
                                            :aria-label="`Selecionar ${disciplina.nome}`"
                                            @update:model-value="(checked) => toggleDisciplina(disciplina.id, checked)"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium">
                                            {{ disciplina.nome }}
                                        </div>
                                        <div
                                            v-if="disciplina.sigla"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ disciplina.sigla }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <select
                                            :value="professorFor(disciplina.id)"
                                            :disabled="!isSelected(disciplina.id)"
                                            class="flex h-10 w-full max-w-md rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                            @change="setProfessor(disciplina.id, ($event.target as HTMLSelectElement).value)"
                                        >
                                            <option value="">Sem professor</option>
                                            <option
                                                v-for="professor in props.professores"
                                                :key="professor.id"
                                                :value="professor.id"
                                            >
                                                {{ professor.nome_completo }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>

                                <tr v-if="props.disciplinas.length === 0">
                                    <td
                                        colspan="3"
                                        class="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        Nenhuma disciplina ativa cadastrada. Cadastre disciplinas antes de montar a grade.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <InputError :message="form.errors.disciplinas" />
                <InputError
                    v-for="(message, key) in form.errors"
                    :key="key"
                    :message="String(key).startsWith('disciplinas.') ? message : ''"
                />

                <div class="flex items-center justify-end gap-2">
                    <Can permission="escola.turmas.editar">
                        <Button
                            type="submit"
                            :disabled="form.processing || props.disciplinas.length === 0"
                            class="flex items-center gap-2"
                        >
                            <Save class="h-4 w-4" />
                            {{ form.processing ? 'Salvando...' : 'Salvar grade' }}
                        </Button>
                    </Can>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
