<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare, Edit, ExternalLink } from 'lucide-vue-next';

interface Aluno {
    id: string;
    nome: string;
    nome_social?: string | null;
}

interface Remetente {
    id: string;
    nome_completo: string;
}

interface Message {
    id: string;
    titulo: string;
    conteudo: string;
    tipo?: string | null;
    prioridade?: string | null;
    anexo_url?: string | null;
    lida: boolean;
    lida_em?: string | null;
    created_at: string;
    aluno?: Aluno | null;
    remetente?: Remetente | null;
}

interface Props {
    message: Message;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: '/school/messages',
    },
    {
        title: props.message.titulo,
        href: `/school/messages/${props.message.id}`,
    },
];

function getPrioridadeBadgeClass(prioridade?: string | null): string {
    switch (prioridade) {
        case 'alta':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'media':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'normal':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        default:
            return 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-400';
    }
}

function getPrioridadeLabel(prioridade?: string | null): string {
    switch (prioridade) {
        case 'alta':
            return 'Alta';
        case 'media':
            return 'Média';
        case 'normal':
            return 'Normal';
        default:
            return 'Normal';
    }
}

function getTipoLabel(tipo?: string | null): string {
    switch (tipo) {
        case 'informativo':
            return 'Informativo';
        case 'atencao':
            return 'Atenção';
        case 'aviso':
            return 'Aviso';
        case 'lembrete':
            return 'Lembrete';
        default:
            return 'Outro';
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Mensagem: ${props.message.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <MessageSquare class="h-5 w-5" />
                            {{ props.message.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes da mensagem
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/messages" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/school/messages/${props.message.id}/edit`" class="flex items-center gap-2">
                            <Edit class="h-4 w-4" />
                            Editar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Informações Básicas</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Título</p>
                                <p class="mt-1">{{ props.message.titulo }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Aluno</p>
                                <p class="mt-1">
                                    {{ props.message.aluno?.nome || '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Tipo</p>
                                <p class="mt-1">{{ getTipoLabel(props.message.tipo) }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Prioridade</p>
                                <p class="mt-1">
                                    <span
                                        :class="getPrioridadeBadgeClass(props.message.prioridade)"
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    >
                                        {{ getPrioridadeLabel(props.message.prioridade) }}
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <p class="mt-1">
                                    <span
                                        v-if="props.message.lida"
                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        Lida
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        Não lida
                                    </span>
                                </p>
                            </div>
                            <div v-if="props.message.lida_em">
                                <p class="text-sm font-medium text-muted-foreground">Lida em</p>
                                <p class="mt-1">{{ props.message.lida_em }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Enviada em</p>
                                <p class="mt-1">{{ props.message.created_at }}</p>
                            </div>
                            <div v-if="props.message.remetente">
                                <p class="text-sm font-medium text-muted-foreground">Remetente</p>
                                <p class="mt-1">
                                    {{ props.message.remetente.nome_completo }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Conteúdo</h3>
                        <div class="rounded-md bg-muted/50 p-4">
                            <p class="whitespace-pre-wrap text-sm">
                                {{ props.message.conteudo }}
                            </p>
                        </div>
                    </div>

                    <div v-if="props.message.anexo_url">
                        <h3 class="mb-4 text-lg font-semibold">Anexo</h3>
                        <Button
                            variant="outline"
                            as-child
                            class="flex items-center gap-2"
                        >
                            <a
                                :href="props.message.anexo_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2"
                            >
                                <ExternalLink class="h-4 w-4" />
                                Abrir anexo
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
