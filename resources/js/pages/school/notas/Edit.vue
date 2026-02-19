<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList } from 'lucide-vue-next';
import NotaForm from './Partials/NotaForm.vue';

interface Nota {
    id: string;
    aluno_id: string;
    professor_id: string;
    turma_id?: string | null;
    disciplina: string;
    disciplina_id?: string | null;
    trimestre: number;
    nota: number;
    frequencia?: number | null;
    comportamento?: string | null;
    observacoes?: string | null;
    ano_letivo: number;
}

interface Aluno {
    id: string;
    nome: string;
}

interface Professor {
    id: string;
    nome_completo: string;
}

interface Turma {
    id: string;
    nome: string;
    ano_letivo?: number | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Props {
    nota: Nota;
    alunos: Aluno[];
    professores: Professor[];
    turmas: Turma[];
    disciplinas: Disciplina[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Notas',
        href: '/school/notas',
    },
    {
        title: 'Editar',
        href: `/school/notas/${props.nota.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar nota: ${props.nota.disciplina}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="`Nota - ${props.nota.disciplina}`"
                        description="Atualize os dados da nota"
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

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/notas/${props.nota.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <NotaForm
                        :nota-data="props.nota"
                        :alunos="props.alunos"
                        :professores="props.professores"
                        :turmas="props.turmas"
                        :disciplinas="props.disciplinas"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
