<script setup lang="ts">
import Can from '@/components/Can.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    CalendarDays,
    CheckCircle2,
    Pencil,
    Send,
    Trash2,
    XCircle,
} from 'lucide-vue-next';

interface Evento {
    id: string;
    titulo: string;
    descricao: string | null;
    valor: string | number;
    vencimento_formatado: string | null;
    publico_label: string;
    status: string;
    status_label: string;
    turma: { id: string; nome: string } | null;
    alunos: { id: string; nome: string }[];
    cobrancas_count: number;
    boleto_url: string | null;
    pix_copia_cola: string | null;
    pix_chave: string | null;
    publicado_em: string | null;
    created_at: string | null;
    pode_editar: boolean;
    pode_publicar: boolean;
    pode_encerrar: boolean;
    pode_excluir: boolean;
}

interface Props {
    evento: Evento;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Financeiro', href: '/school/cobrancas' },
    { title: 'Eventos', href: '/school/cobrancas/eventos' },
    { title: props.evento.titulo, href: `/school/cobrancas/eventos/${props.evento.id}` },
];

function formatValor(valor: string | number): string {
    const numeric = typeof valor === 'number' ? valor : Number(valor);

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(numeric) ? numeric : 0);
}

function getStatusVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (value === 'publicado') {
        return 'default';
    }
    if (value === 'encerrado') {
        return 'outline';
    }
    return 'secondary';
}

function publicar() {
    if (
        !confirm(
            'Publicar este evento? Serão geradas cobranças para todos os alunos do público selecionado.',
        )
    ) {
        return;
    }

    router.post(`/school/cobrancas/eventos/${props.evento.id}/publicar`);
}

function notificarResponsaveis() {
    if (
        !confirm(
            'Criar um comunicado publicado para os responsáveis sobre este evento?',
        )
    ) {
        return;
    }

    router.post(`/school/cobrancas/eventos/${props.evento.id}/notificar`);
}

function encerrar() {
    if (!confirm('Encerrar este evento?')) {
        return;
    }

    router.patch(`/school/cobrancas/eventos/${props.evento.id}/encerrar`);
}

function excluir() {
    if (!confirm('Excluir este rascunho de evento?')) {
        return;
    }

    router.delete(`/school/cobrancas/eventos/${props.evento.id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Evento: ${props.evento.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <CalendarDays class="h-5 w-5" />
                            {{ props.evento.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes do evento e cobranças geradas
                        </p>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <Can v-if="props.evento.pode_editar" permission="escola.financeiro.editar">
                        <Button variant="outline" as-child class="rounded-lg">
                            <Link
                                :href="`/school/cobrancas/eventos/${props.evento.id}/edit`"
                                class="flex items-center gap-2"
                            >
                                <Pencil class="h-4 w-4" />
                                Editar
                            </Link>
                        </Button>
                    </Can>
                    <Button variant="outline" as-child class="rounded-lg">
                        <Link href="/school/cobrancas/eventos" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Status</p>
                        <div class="mt-1">
                            <Badge :variant="getStatusVariant(props.evento.status)">
                                {{ props.evento.status_label }}
                            </Badge>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Valor</p>
                        <p class="mt-1 font-semibold">{{ formatValor(props.evento.valor) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Vencimento</p>
                        <p class="mt-1">{{ props.evento.vencimento_formatado || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Público</p>
                        <p class="mt-1">
                            {{ props.evento.publico_label }}
                            <span v-if="props.evento.turma"> — {{ props.evento.turma.nome }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Cobranças geradas</p>
                        <p class="mt-1">
                            <Link
                                :href="`/school/cobrancas?tipo=evento`"
                                class="text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ props.evento.cobrancas_count }} cobrança(s)
                            </Link>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Publicado em</p>
                        <p class="mt-1">{{ props.evento.publicado_em || '—' }}</p>
                    </div>
                    <div v-if="props.evento.descricao" class="sm:col-span-2">
                        <p class="text-sm font-medium text-muted-foreground">Descrição</p>
                        <p class="mt-1 whitespace-pre-wrap">{{ props.evento.descricao }}</p>
                    </div>
                    <div v-if="props.evento.alunos.length > 0" class="sm:col-span-2">
                        <p class="text-sm font-medium text-muted-foreground">Alunos selecionados</p>
                        <p class="mt-1 text-sm">
                            {{ props.evento.alunos.map((aluno) => aluno.nome).join(', ') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Chave PIX</p>
                        <p class="mt-1 break-all">{{ props.evento.pix_chave || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Boleto</p>
                        <a
                            v-if="props.evento.boleto_url"
                            :href="props.evento.boleto_url"
                            target="_blank"
                            class="mt-1 inline-flex text-sm text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Abrir PDF
                        </a>
                        <p v-else class="mt-1 text-sm text-muted-foreground">Nenhum boleto</p>
                    </div>
                    <div v-if="props.evento.pix_copia_cola" class="sm:col-span-2">
                        <p class="text-sm font-medium text-muted-foreground">PIX copia e cola</p>
                        <p class="mt-1 break-all text-sm">{{ props.evento.pix_copia_cola }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Can v-if="props.evento.pode_publicar" permission="escola.financeiro.criar">
                    <Button class="gap-2" @click="publicar">
                        <Send class="h-4 w-4" />
                        Publicar e gerar cobranças
                    </Button>
                </Can>
                <Can
                    v-if="props.evento.status !== 'rascunho'"
                    permission="escola.financeiro.criar"
                >
                    <Button variant="outline" class="gap-2" @click="notificarResponsaveis">
                        <Bell class="h-4 w-4" />
                        Notificar responsáveis
                    </Button>
                </Can>
                <Can v-if="props.evento.pode_encerrar" permission="escola.financeiro.editar">
                    <Button variant="outline" class="gap-2" @click="encerrar">
                        <CheckCircle2 class="h-4 w-4" />
                        Encerrar evento
                    </Button>
                </Can>
                <Can v-if="props.evento.pode_excluir" permission="escola.financeiro.excluir">
                    <Button variant="destructive" class="gap-2" @click="excluir">
                        <Trash2 class="h-4 w-4" />
                        Excluir rascunho
                    </Button>
                </Can>
                <Button
                    v-if="props.evento.status === 'publicado'"
                    variant="outline"
                    as-child
                    class="gap-2"
                >
                    <Link href="/school/cobrancas?tipo=evento">
                        <XCircle class="h-4 w-4" />
                        Ver cobranças
                    </Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
