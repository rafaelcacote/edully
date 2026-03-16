<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, AlertTriangle, UserCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import TeacherForm from './Partials/TeacherForm.vue';

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Props {
    disciplinas: Disciplina[];
}

const props = defineProps<Props>();

const hasNoDisciplinas = computed(() => props.disciplinas.length === 0);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Professores',
        href: '/school/teachers',
    },
    {
        title: 'Novo professor',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Novo professor" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Novo professor"
                        description="Cadastre um novo professor"
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

            <Alert
                v-if="hasNoDisciplinas"
                variant="destructive"
                class="rounded-xl border-amber-500/50 bg-amber-500/10 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200 [&>svg]:text-amber-600 dark:[&>svg]:text-amber-400"
            >
                <AlertTriangle class="size-4 shrink-0" />
                <AlertTitle>Cadastre disciplinas primeiro</AlertTitle>
                <AlertDescription>
                    Não há nenhuma disciplina ativa cadastrada nesta escola. Cadastre ao menos uma
                    disciplina antes de cadastrar um professor, para poder vinculá-lo às disciplinas.
                    <Link
                        href="/school/disciplinas/create"
                        class="ml-1 font-medium underline underline-offset-2 hover:no-underline"
                    >
                        Ir para cadastro de disciplinas
                    </Link>
                </AlertDescription>
            </Alert>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    action="/school/teachers"
                    method="post"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <TeacherForm
                        submit-label="Criar professor"
                        :processing="processing"
                        :errors="errors"
                        :disciplinas="props.disciplinas"
                        :submit-disabled="hasNoDisciplinas"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

