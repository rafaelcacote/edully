<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen } from 'lucide-vue-next';
import ClassForm from './Partials/ClassForm.vue';

interface Class {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    capacidade?: number | null;
    ano_letivo?: number | null;
    professor_id?: string | null;
    professor_ids?: string[];
    ativo: boolean;
}

interface Teacher {
    id: string;
    nome_completo: string;
}

interface Props {
    turma: Class;
    teachers: Teacher[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Turmas',
        href: '/school/classes',
    },
    {
        title: 'Editar',
        href: `/school/classes/${props.turma.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar turma: ${props.turma.nome}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.turma.nome"
                        description="Atualize os dados da turma"
                        :icon="BookOpen"
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

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/classes/${props.turma.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <ClassForm
                        :class-data="props.turma"
                        :teachers="props.teachers"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

