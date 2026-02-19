<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare } from 'lucide-vue-next';
import MessageForm from './Partials/MessageForm.vue';

interface Aluno {
    id: string;
    nome: string;
}

interface Turma {
    id: string;
    nome: string;
}

interface Props {
    alunos: Aluno[];
    turmas: Turma[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: '/school/messages',
    },
    {
        title: 'Nova mensagem',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Nova mensagem" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Nova mensagem"
                        description="Envie uma nova mensagem para um aluno"
                        :icon="MessageSquare"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/messages" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    action="/school/messages"
                    method="post"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <MessageForm
                        :alunos="props.alunos"
                        :turmas="props.turmas"
                        submit-label="Enviar mensagem"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
