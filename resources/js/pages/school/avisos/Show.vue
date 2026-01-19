<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bell, Edit } from 'lucide-vue-next';

interface User {
    id: string;
    nome_completo: string;
}

interface Aviso {
    id: string;
    titulo: string;
    conteudo: string;
    prioridade: string;
    publico_alvo: string;
    anexo_url: string | null;
    publicado: boolean;
    publicado_em: string | null;
    expira_em: string | null;
    created_at: string;
    updated_at: string;
    criado_por?: User | null;
}

interface Props {
    aviso: Aviso;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Avisos',
        href: '/school/avisos',
    },
    {
        title: props.aviso.titulo,
        href: `/school/avisos/${props.aviso.id}`,
    },
];

function getPrioridadeLabel(prioridade: string): string {
    const labels: Record<string, string> = {
        normal: 'Normal',
        alta: 'Alta',
        media: 'Média',
    };
    return labels[prioridade] || prioridade;
}

function getPrioridadeVariant(prioridade: string): string {
    const variants: Record<string, string> = {
        normal: 'default',
        alta: 'destructive',
        media: 'secondary',
    };
    return variants[prioridade] || 'default';
}

function getPublicoAlvoLabel(publicoAlvo: string): string {
    const labels: Record<string, string> = {
        todos: 'Todos',
        alunos: 'Alunos',
        professores: 'Professores',
        responsaveis: 'Responsáveis',
    };
    return labels[publicoAlvo] || publicoAlvo;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Aviso: ${props.aviso.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <Bell class="h-5 w-5" />
                            {{ props.aviso.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes do aviso
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/avisos" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link
                            :href="`/school/avisos/${props.aviso.id}/edit`"
                            class="flex items-center gap-2"
                        >
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
                                <p class="mt-1">{{ props.aviso.titulo }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Prioridade</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="getPrioridadeVariant(props.aviso.prioridade)"
                                    >
                                        {{ getPrioridadeLabel(props.aviso.prioridade) }}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Público-alvo</p>
                                <p class="mt-1">
                                    {{ getPublicoAlvoLabel(props.aviso.publico_alvo) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.aviso.publicado ? 'default' : 'secondary'"
                                    >
                                        {{ props.aviso.publicado ? 'Publicado' : 'Não publicado' }}
                                    </Badge>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Publicado em</p>
                                <p class="mt-1">
                                    {{
                                        props.aviso.publicado_em
                                            ? new Date(props.aviso.publicado_em).toLocaleString('pt-BR')
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Expira em</p>
                                <p class="mt-1">
                                    {{
                                        props.aviso.expira_em
                                            ? new Date(props.aviso.expira_em).toLocaleString('pt-BR')
                                            : '—'
                                    }}
                                </p>
                            </div>
                            <div v-if="props.aviso.anexo_url">
                                <p class="text-sm font-medium text-muted-foreground">Anexo</p>
                                <p class="mt-1">
                                    <a
                                        :href="props.aviso.anexo_url"
                                        target="_blank"
                                        class="text-blue-500 hover:underline"
                                    >
                                        Ver anexo
                                    </a>
                                </p>
                            </div>
                            <div v-if="props.aviso.criado_por">
                                <p class="text-sm font-medium text-muted-foreground">Criado por</p>
                                <p class="mt-1">{{ props.aviso.criado_por.nome_completo }}</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Conteúdo</h3>
                        <div class="rounded-lg border bg-muted/50 p-4">
                            <p class="whitespace-pre-wrap text-sm">{{ props.aviso.conteudo }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
