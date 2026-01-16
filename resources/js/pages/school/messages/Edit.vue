<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare } from 'lucide-vue-next';
import MessageForm from './Partials/MessageForm.vue';

interface Message {
    id: string;
    aluno_id: string;
    titulo: string;
    conteudo?: string | null;
    tipo?: string | null;
    prioridade?: string | null;
    anexo_url?: string | null;
}

interface Aluno {
    id: string;
    nome: string;
}

interface Props {
    message: Message;
    alunos: Aluno[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: '/school/messages',
    },
    {
        title: 'Editar',
        href: `/school/messages/${props.message.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar mensagem: ${props.message.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.message.titulo"
                        description="Atualize os dados da mensagem"
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
                    :action="`/school/messages/${props.message.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <MessageForm
                        :message-data="props.message"
                        :alunos="props.alunos"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
