<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck } from 'lucide-vue-next';
import TestForm from './Partials/TestForm.vue';

interface Test {
    id: string;
    disciplina_id: string;
    titulo: string;
    descricao?: string | null;
    data_prova: string;
    horario?: string | null;
    sala?: string | null;
    duracao_minutos?: number | null;
    turma_id: string;
}

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    ano_letivo?: number | string | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Props {
    test: Test;
    turmas: Turma[];
    disciplinas: Disciplina[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Provas',
        href: '/school/tests',
    },
    {
        title: 'Editar',
        href: `/school/tests/${props.test.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar prova: ${props.test.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.test.titulo"
                        description="Atualize os dados da prova"
                        :icon="ClipboardCheck"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/tests" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/tests/${props.test.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <TestForm
                        :test-data="props.test"
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

