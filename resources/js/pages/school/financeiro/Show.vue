<script setup lang="ts">
import Can from '@/components/Can.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    CheckCircle2,
    CreditCard,
    Pencil,
    XCircle,
} from 'lucide-vue-next';

interface Cobranca {
    id: string;
    titulo: string;
    descricao: string | null;
    tipo: string;
    tipo_label: string;
    referencia: string | null;
    valor: string | number;
    vencimento: string | null;
    vencimento_formatado: string | null;
    status: string;
    status_exibicao: string;
    status_label: string;
    esta_atrasada: boolean;
    aluno: { id: string; nome: string } | null;
    boleto_url: string | null;
    pix_copia_cola: string | null;
    pix_chave: string | null;
    pix_qrcode_url: string | null;
    pago_em: string | null;
    pago_em_formatado: string | null;
    pago_observacao: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    cobranca: Cobranca;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Financeiro',
        href: '/school/cobrancas',
    },
    {
        title: props.cobranca.titulo,
        href: `/school/cobrancas/${props.cobranca.id}`,
    },
];

function getStatusVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (value === 'atrasado' || value === 'cancelado') {
        return 'destructive';
    }
    if (value === 'pago') {
        return 'default';
    }
    return 'secondary';
}

function formatValor(valor: string | number): string {
    const numeric = typeof valor === 'number' ? valor : Number(valor);

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number.isFinite(numeric) ? numeric : 0);
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('pt-BR');
}

function cancelCobranca() {
    if (!confirm('Tem certeza que deseja cancelar esta cobrança?')) {
        return;
    }

    router.patch(`/school/cobrancas/${props.cobranca.id}/cancelar`, {}, { preserveScroll: true });
}

function notificarResponsaveis() {
    if (
        !confirm(
            'Criar um comunicado publicado para os responsáveis sobre esta cobrança?',
        )
    ) {
        return;
    }

    router.post(`/school/cobrancas/${props.cobranca.id}/notificar`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Cobrança: ${props.cobranca.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <CreditCard class="h-5 w-5" />
                            {{ props.cobranca.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes da cobrança e confirmação de pagamento
                        </p>
                    </div>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <Can
                        v-if="props.cobranca.status !== 'cancelado'"
                        permission="escola.financeiro.criar"
                    >
                        <Button
                            variant="outline"
                            class="gap-2 rounded-lg"
                            @click="notificarResponsaveis"
                        >
                            <Bell class="h-4 w-4" />
                            Notificar responsáveis
                        </Button>
                    </Can>
                    <Can permission="escola.financeiro.editar">
                        <Button variant="outline" as-child class="rounded-lg">
                            <Link
                                :href="`/school/cobrancas/${props.cobranca.id}/edit`"
                                class="flex items-center gap-2"
                            >
                                <Pencil class="h-4 w-4" />
                                Editar
                            </Link>
                        </Button>
                    </Can>
                    <Button variant="outline" as-child class="rounded-lg">
                        <Link href="/school/cobrancas" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Informações</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Aluno</p>
                                <p class="mt-1">{{ props.cobranca.aluno?.nome || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Tipo</p>
                                <p class="mt-1">{{ props.cobranca.tipo_label }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Valor</p>
                                <p class="mt-1 font-semibold">
                                    {{ formatValor(props.cobranca.valor) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Vencimento</p>
                                <p class="mt-1">
                                    {{ props.cobranca.vencimento_formatado || '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="getStatusVariant(props.cobranca.status_exibicao)"
                                    >
                                        {{ props.cobranca.status_label }}
                                    </Badge>
                                </div>
                            </div>
                            <div v-if="props.cobranca.referencia">
                                <p class="text-sm font-medium text-muted-foreground">Referência</p>
                                <p class="mt-1">{{ props.cobranca.referencia }}</p>
                            </div>
                            <div v-if="props.cobranca.descricao" class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Descrição</p>
                                <p class="mt-1 whitespace-pre-wrap">{{ props.cobranca.descricao }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Criado em</p>
                                <p class="mt-1">{{ formatDateTime(props.cobranca.created_at) }}</p>
                            </div>
                            <div v-if="props.cobranca.pago_em_formatado">
                                <p class="text-sm font-medium text-muted-foreground">Pago em</p>
                                <p class="mt-1">{{ props.cobranca.pago_em_formatado }}</p>
                            </div>
                            <div v-if="props.cobranca.pago_observacao" class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Observação</p>
                                <p class="mt-1 whitespace-pre-wrap">
                                    {{ props.cobranca.pago_observacao }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Pagamento</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Boleto</p>
                                <a
                                    v-if="props.cobranca.boleto_url"
                                    :href="props.cobranca.boleto_url"
                                    target="_blank"
                                    class="mt-1 inline-flex text-sm text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Abrir PDF do boleto
                                </a>
                                <p v-else class="mt-1 text-sm text-muted-foreground">
                                    Nenhum boleto anexado
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Chave PIX</p>
                                <p class="mt-1 break-all">
                                    {{ props.cobranca.pix_chave || '—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">
                                    PIX copia e cola
                                </p>
                                <p class="mt-1 break-all text-sm">
                                    {{ props.cobranca.pix_copia_cola || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Can
                v-if="props.cobranca.status !== 'pago' && props.cobranca.status !== 'cancelado'"
                permission="escola.financeiro.editar"
            >
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h3 class="mb-4 text-lg font-semibold">Confirmar pagamento</h3>
                    <Form
                        :action="`/school/cobrancas/${props.cobranca.id}/pagar`"
                        method="patch"
                        class="space-y-4"
                        #default="{ errors, processing }"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="pago_em">Data do pagamento</Label>
                                <Input id="pago_em" name="pago_em" type="date" />
                                <InputError :message="errors.pago_em" />
                            </div>
                            <div class="space-y-2 sm:col-span-2">
                                <Label for="pago_observacao">Observação (opcional)</Label>
                                <textarea
                                    id="pago_observacao"
                                    name="pago_observacao"
                                    rows="3"
                                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    placeholder="Ex.: pago via PIX, comprovante recebido..."
                                />
                                <InputError :message="errors.pago_observacao" />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="processing" class="gap-2">
                                <CheckCircle2 class="h-4 w-4" />
                                {{ processing ? 'Confirmando...' : 'Marcar como pago' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                @click="cancelCobranca"
                            >
                                <XCircle class="h-4 w-4" />
                                Cancelar cobrança
                            </Button>
                        </div>
                    </Form>
                </div>
            </Can>
        </div>
    </AppLayout>
</template>
