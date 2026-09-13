<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, UserCheck } from 'lucide-vue-next';
import TeacherForm from './Partials/TeacherForm.vue';

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Teacher {
    id: string;
    matricula: string;
    disciplinas?: string[] | null;
    especializacao?: string | null;
    ativo: boolean;
    nome_completo?: string;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
    foto_url?: string | null;
}

interface Props {
    teacher: Teacher;
    disciplinas: Disciplina[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Professores',
        href: '/school/teachers',
    },
    {
        title: 'Editar',
        href: `/school/teachers/${props.teacher.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar professor: ${props.teacher.nome_completo || props.teacher.matricula}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.teacher.nome_completo || props.teacher.matricula"
                        description="Atualize os dados do professor"
                        :icon="UserCheck"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/teachers" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/teachers/${props.teacher.id}`"
                    method="post"
                    enctype="multipart/form-data"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="_method" value="patch" />
                    <TeacherForm
                        :teacher="props.teacher"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                        :disciplinas="props.disciplinas"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>




