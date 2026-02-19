<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
function index() {
    return { url: '/messages' };
}

function messagesEdit(args: { message: string }) {
    return { url: `/messages/${args.message}/edit` };
}
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from 'lucide-vue-next';
import MessageForm from './Partials/MessageForm.vue';

interface User {
    id: string;
    nome_completo: string;
    email: string;
}

interface Message {
    id: string;
    destinatario_id?: string;
    titulo: string;
    conteudo: string;
    ativo: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    message: Message;
    users: User[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: index().url,
    },
    {
        title: 'Editar',
        href: messagesEdit({ message: props.message.id }).url,
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
                        :icon="Mail"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link :href="index()" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/messages/${props.message.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <MessageForm
                        :message="props.message"
                        :users="props.users"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>

                <div class="mt-6 border-t pt-6">
                    <p class="text-sm font-medium">Dados do sistema</p>
                    <div
                        class="mt-2 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2"
                    >
                        <div>
                            <span class="font-medium text-foreground">Criado em:</span>
                            {{ new Date(props.message.created_at).toLocaleString('pt-BR') }}
                        </div>
                        <div>
                            <span class="font-medium text-foreground">Atualizado em:</span>
                            {{ new Date(props.message.updated_at).toLocaleString('pt-BR') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
