<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList } from 'lucide-vue-next';
import NotaForm from './Partials/NotaForm.vue';

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
        title: 'Nova nota',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Nova nota" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Nova nota"
                        description="Cadastre uma nova nota"
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
                    action="/school/notas"
                    method="post"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <NotaForm
                        :alunos="props.alunos"
                        :professores="props.professores"
                        :turmas="props.turmas"
                        :disciplinas="props.disciplinas"
                        submit-label="Criar nota"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
