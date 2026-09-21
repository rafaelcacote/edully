<script setup lang="ts">
import Can from '@/components/Can.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Bell,
    Check,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    FileText,
    FolderOpen,
    Lock,
    Save,
    Stethoscope,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Option {
    value: string;
    label: string;
}

interface Professor {
    id: string;
    nome: string;
    disciplinas: string[];
}

interface Documento {
    id: string;
    titulo: string;
    descricao: string | null;
    tipo: string;
    tipo_label: string;
    status: string;
    status_label: string;
    data_inicio: string | null;
    data_fim: string | null;
    categoria_declaracao: string | null;
    categoria_declaracao_label?: string | null;
    anexo_url: string | null;
    anexo_resposta_url: string | null;
    motivo_recusa: string | null;
    aluno: { id: string; nome: string } | null;
    criado_por: { id: string; nome_completo: string } | null;
    created_at: string | null;
    updated_at: string | null;
    pode_analisar: boolean;
    pode_notificar_professores: boolean;
    professores_ja_notificados: boolean;
    professores_notificados_em: string | null;
    professores_notificados_por: { id: string; nome_completo: string } | null;
    professores_notificados_ids: string[];
}

interface Props {
    documento: Documento;
    statusOptions: Option[];
    professores?: Professor[];
}

interface StepItem {
    id: number;
    title: string;
    description: string;
}

const props = withDefaults(defineProps<Props>(), {
    professores: () => [],
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documentos',
        href: '/school/documentos',
    },
    {
        title: props.documento.titulo,
        href: `/school/documentos/${props.documento.id}`,
    },
];

const isAtestado = computed(() => props.documento.tipo === 'atestado');
const isPedido = computed(() => props.documento.tipo === 'pedido_declaracao');

const status = computed(() => props.documento.status);
const isApproved = computed(() =>
    ['aprovado', 'atendido'].includes(status.value),
);
const isRefused = computed(() =>
    ['recusado', 'cancelado'].includes(status.value),
);
const isPendingReview = computed(() =>
    ['enviado', 'em_analise'].includes(status.value),
);

const atestadoSteps = computed<StepItem[]>(() => [
    {
        id: 1,
        title: 'Revisar',
        description: 'Conferir o atestado enviado',
    },
    {
        id: 2,
        title: 'Decidir',
        description: 'Aprovar ou recusar',
    },
    {
        id: 3,
        title: 'Notificar',
        description: props.documento.professores_ja_notificados
            ? 'Professores já avisados'
            : 'Avisar os professores',
    },
]);

const pedidoSteps = computed<StepItem[]>(() => [
    {
        id: 1,
        title: 'Revisar',
        description: 'Conferir o pedido',
    },
    {
        id: 2,
        title: 'Atender',
        description: 'Responder o responsável',
    },
]);

const steps = computed(() =>
    isAtestado.value ? atestadoSteps.value : pedidoSteps.value,
);

function suggestedStep(): number {
    if (isAtestado.value) {
        if (isApproved.value) {
            return 3;
        }
        if (status.value === 'em_analise' || isRefused.value) {
            return 2;
        }
        return 1;
    }

    if (isPedido.value) {
        if (isApproved.value || isRefused.value || status.value === 'atendido') {
            return 2;
        }
        return status.value === 'em_analise' ? 2 : 1;
    }

    return 1;
}

const activeStep = ref(suggestedStep());

watch(
    () => props.documento.status,
    () => {
        activeStep.value = suggestedStep();
    },
);

const showRefuseForm = ref(status.value === 'recusado');
const selectedStatus = ref(
    props.statusOptions.some((option) => option.value === props.documento.status)
        ? props.documento.status
        : (props.statusOptions[0]?.value ?? ''),
);
const showMotivoRecusa = computed(
    () => selectedStatus.value === 'recusado' || showRefuseForm.value,
);
const selectedProfessorIds = ref<string[]>([]);

const allProfessoresSelected = computed(
    () =>
        props.professores.length > 0 &&
        selectedProfessorIds.value.length === props.professores.length,
);

const step3Unlocked = computed(() => isApproved.value);
const professoresJaNotificados = computed(
    () => props.documento.professores_ja_notificados,
);
const professoresNotificadosIds = computed(
    () => props.documento.professores_notificados_ids ?? [],
);

function professorJaNotificado(id: string): boolean {
    return professoresNotificadosIds.value.includes(id);
}

function stepState(stepId: number): 'complete' | 'current' | 'upcoming' | 'locked' {
    if (isAtestado.value && stepId === 3 && !step3Unlocked.value) {
        return activeStep.value === 3 ? 'current' : 'locked';
    }

    if (isAtestado.value) {
        if (stepId === 1 && !['enviado'].includes(status.value)) {
            return activeStep.value === 1 ? 'current' : 'complete';
        }
        if (stepId === 2) {
            if (isApproved.value || isRefused.value) {
                return activeStep.value === 2 ? 'current' : 'complete';
            }
            if (status.value === 'em_analise' || activeStep.value === 2) {
                return activeStep.value === 2 ? 'current' : 'upcoming';
            }
        }
        if (stepId === 3 && step3Unlocked.value) {
            if (professoresJaNotificados.value) {
                return activeStep.value === 3 ? 'current' : 'complete';
            }

            return activeStep.value === 3 ? 'current' : 'upcoming';
        }
    }

    if (activeStep.value === stepId) {
        return 'current';
    }
    if (stepId < activeStep.value) {
        return 'complete';
    }
    return 'upcoming';
}

function selectStep(stepId: number) {
    if (isAtestado.value && stepId === 3 && !step3Unlocked.value) {
        return;
    }
    activeStep.value = stepId;
}

function getStatusVariant(
    value: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (value === 'recusado' || value === 'cancelado') {
        return 'destructive';
    }
    if (value === 'aprovado' || value === 'atendido' || value === 'disponivel') {
        return 'default';
    }
    if (value === 'em_analise') {
        return 'outline';
    }
    return 'secondary';
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('pt-BR');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');
    if (!year || !month || !day) {
        return value;
    }

    return `${day}/${month}/${year}`;
}

function toggleProfessor(id: string) {
    if (selectedProfessorIds.value.includes(id)) {
        selectedProfessorIds.value = selectedProfessorIds.value.filter(
            (item) => item !== id,
        );
    } else {
        selectedProfessorIds.value = [...selectedProfessorIds.value, id];
    }
}

function toggleAllProfessores() {
    if (allProfessoresSelected.value) {
        selectedProfessorIds.value = [];
    } else {
        selectedProfessorIds.value = props.professores.map((professor) => professor.id);
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Documento: ${props.documento.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <component
                                :is="isAtestado ? Stethoscope : FolderOpen"
                                class="h-5 w-5"
                            />
                            {{ props.documento.titulo }}
                        </h2>
                        <Badge :variant="getStatusVariant(props.documento.status)">
                            {{ props.documento.status_label }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="isAtestado">
                            Fluxo guiado para analisar o atestado e avisar a turma
                        </template>
                        <template v-else-if="isPedido">
                            Fluxo para revisar o pedido e responder o responsável
                        </template>
                        <template v-else>
                            Detalhes do documento
                        </template>
                    </p>
                </div>

                <Button variant="outline" as-child class="rounded-lg">
                    <Link href="/school/documentos" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <!-- Stepper (atestado / pedido) -->
            <div
                v-if="props.documento.pode_analisar"
                class="rounded-xl border bg-card p-4 shadow-sm sm:p-6"
            >
                <ol
                    class="grid gap-3"
                    :class="steps.length === 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2'"
                >
                    <li
                        v-for="(step, index) in steps"
                        :key="step.id"
                        class="relative"
                    >
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors"
                            :class="{
                                'border-primary bg-primary/5': stepState(step.id) === 'current',
                                'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/30':
                                    stepState(step.id) === 'complete',
                                'border-dashed opacity-60': stepState(step.id) === 'locked',
                                'hover:bg-muted/40': stepState(step.id) !== 'locked',
                                'cursor-not-allowed': stepState(step.id) === 'locked',
                            }"
                            :disabled="stepState(step.id) === 'locked'"
                            @click="selectStep(step.id)"
                        >
                            <span
                                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold"
                                :class="{
                                    'border-primary bg-primary text-primary-foreground':
                                        stepState(step.id) === 'current',
                                    'border-emerald-500 bg-emerald-500 text-white':
                                        stepState(step.id) === 'complete',
                                    'border-muted-foreground/30 text-muted-foreground':
                                        stepState(step.id) === 'upcoming' ||
                                        stepState(step.id) === 'locked',
                                }"
                            >
                                <Lock
                                    v-if="stepState(step.id) === 'locked'"
                                    class="h-3.5 w-3.5"
                                />
                                <Check
                                    v-else-if="stepState(step.id) === 'complete'"
                                    class="h-4 w-4"
                                />
                                <span v-else>{{ index + 1 }}</span>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold">{{ step.title }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">
                                    {{ step.description }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ol>
            </div>

            <!-- Resumo do aluno / documento -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Aluno</p>
                        <p class="mt-1 font-medium">{{ props.documento.aluno?.nome || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Tipo</p>
                        <p class="mt-1">{{ props.documento.tipo_label }}</p>
                    </div>
                    <div v-if="props.documento.data_inicio || props.documento.data_fim">
                        <p class="text-sm font-medium text-muted-foreground">Período</p>
                        <p class="mt-1">
                            {{ formatDate(props.documento.data_inicio) }} —
                            {{ formatDate(props.documento.data_fim) }}
                        </p>
                    </div>
                    <div v-if="props.documento.categoria_declaracao_label">
                        <p class="text-sm font-medium text-muted-foreground">Categoria</p>
                        <p class="mt-1">{{ props.documento.categoria_declaracao_label }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Recebido em</p>
                        <p class="mt-1">{{ formatDateTime(props.documento.created_at) }}</p>
                    </div>
                    <div v-if="props.documento.criado_por">
                        <p class="text-sm font-medium text-muted-foreground">Enviado por</p>
                        <p class="mt-1">{{ props.documento.criado_por.nome_completo }}</p>
                    </div>
                </div>

                <div v-if="props.documento.descricao" class="mt-5">
                    <p class="mb-2 text-sm font-medium text-muted-foreground">Observações</p>
                    <div class="rounded-lg border bg-muted/40 p-4">
                        <p class="whitespace-pre-wrap text-sm">{{ props.documento.descricao }}</p>
                    </div>
                </div>

                <div
                    v-if="props.documento.motivo_recusa"
                    class="mt-5 rounded-lg border border-destructive/30 bg-destructive/5 p-4"
                >
                    <p class="text-sm font-medium text-destructive">Motivo da recusa</p>
                    <p class="mt-1 whitespace-pre-wrap text-sm">
                        {{ props.documento.motivo_recusa }}
                    </p>
                </div>
            </div>

            <!-- Step 1: Revisar -->
            <div
                v-if="props.documento.pode_analisar && activeStep === 1"
                class="rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="mb-5 flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300"
                    >
                        <FileText class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">1. Revisar documento</h3>
                        <p class="text-sm text-muted-foreground">
                            Confira o anexo enviado pelo responsável antes de decidir.
                        </p>
                    </div>
                </div>

                <div
                    v-if="props.documento.anexo_url"
                    class="mb-6 flex flex-col gap-3 rounded-lg border border-dashed bg-muted/30 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-3">
                        <FileText class="h-8 w-8 text-sky-600" />
                        <div>
                            <p class="font-medium">Anexo do responsável</p>
                            <p class="text-sm text-muted-foreground">
                                Abra em uma nova aba para analisar com calma
                            </p>
                        </div>
                    </div>
                    <Button as-child>
                        <a :href="props.documento.anexo_url" target="_blank" rel="noopener">
                            Abrir anexo
                        </a>
                    </Button>
                </div>
                <p v-else class="mb-6 text-sm text-muted-foreground">
                    Este envio não possui anexo.
                </p>

                <Can permission="escola.documentos.editar">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <Form
                            v-if="status === 'enviado'"
                            :action="`/school/documentos/${props.documento.id}/status`"
                            method="post"
                            class="inline"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="em_analise" />
                            <Button type="submit" variant="secondary" :disabled="processing">
                                <ClipboardCheck class="mr-2 h-4 w-4" />
                                {{ processing ? 'Salvando...' : 'Marcar em análise' }}
                            </Button>
                        </Form>

                        <Button type="button" @click="selectStep(2)">
                            Continuar para decisão
                        </Button>
                    </div>
                </Can>
            </div>

            <!-- Step 2 Atestado: Decidir -->
            <div
                v-if="isAtestado && activeStep === 2"
                class="rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="mb-5 flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        <ClipboardCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">2. Decidir sobre o atestado</h3>
                        <p class="text-sm text-muted-foreground">
                            Aprove para liberar a notificação aos professores, ou recuse com
                            motivo.
                        </p>
                    </div>
                </div>

                <div
                    v-if="isApproved"
                    class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0" />
                    Atestado já aprovado. Você pode seguir para notificar os professores.
                </div>

                <div
                    v-else-if="isRefused"
                    class="mb-4 rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm"
                >
                    Este atestado foi recusado.
                    <span v-if="props.documento.motivo_recusa" class="mt-1 block opacity-90">
                        Motivo: {{ props.documento.motivo_recusa }}
                    </span>
                </div>

                <Can v-if="isPendingReview" permission="escola.documentos.editar">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Form
                            :action="`/school/documentos/${props.documento.id}/status`"
                            method="post"
                            class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 dark:border-emerald-900 dark:bg-emerald-950/20"
                            v-slot="{ processing }"
                        >
                            <input type="hidden" name="status" value="aprovado" />
                            <h4 class="font-semibold text-emerald-900 dark:text-emerald-100">
                                Aprovar
                            </h4>
                            <p class="mt-1 text-sm text-emerald-800/80 dark:text-emerald-200/80">
                                Confirma que o atestado é válido. Em seguida você avisará os
                                professores.
                            </p>
                            <Button
                                type="submit"
                                class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700"
                                :disabled="processing"
                            >
                                <Check class="mr-2 h-4 w-4" />
                                {{ processing ? 'Aprovando...' : 'Aprovar atestado' }}
                            </Button>
                        </Form>

                        <div
                            class="rounded-xl border border-destructive/20 bg-destructive/5 p-5"
                        >
                            <h4 class="font-semibold">Recusar</h4>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Use se o anexo estiver ilegível, incompleto ou inválido.
                            </p>

                            <Button
                                v-if="!showRefuseForm"
                                type="button"
                                variant="outline"
                                class="mt-4 w-full"
                                @click="showRefuseForm = true"
                            >
                                Quero recusar
                            </Button>

                            <Form
                                v-else
                                :action="`/school/documentos/${props.documento.id}/status`"
                                method="post"
                                class="mt-4 space-y-3"
                                v-slot="{ errors, processing }"
                            >
                                <input type="hidden" name="status" value="recusado" />
                                <div class="space-y-2">
                                    <Label for="motivo_recusa">Motivo da recusa *</Label>
                                    <textarea
                                        id="motivo_recusa"
                                        name="motivo_recusa"
                                        rows="3"
                                        required
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder="Explique o motivo para o responsável..."
                                    />
                                    <InputError :message="errors.motivo_recusa" />
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        @click="showRefuseForm = false"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                    >
                                        {{ processing ? 'Recusando...' : 'Confirmar recusa' }}
                                    </Button>
                                </div>
                            </Form>
                        </div>
                    </div>
                </Can>

                <div
                    v-if="isApproved"
                    class="mt-4 flex justify-end"
                >
                    <Button type="button" @click="selectStep(3)">
                        Ir para notificar professores
                    </Button>
                </div>
            </div>

            <!-- Step 2 Pedido: Atender -->
            <div
                v-if="isPedido && activeStep === 2"
                class="rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="mb-5 flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200"
                    >
                        <ClipboardCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">2. Atender pedido</h3>
                        <p class="text-sm text-muted-foreground">
                            Atualize o status e, se quiser, anexe a declaração gerada.
                        </p>
                    </div>
                </div>

                <Can permission="escola.documentos.editar">
                    <Form
                        :action="`/school/documentos/${props.documento.id}/status`"
                        method="post"
                        enctype="multipart/form-data"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-2">
                            <Label for="status">Novo status *</Label>
                            <select
                                id="status"
                                name="status"
                                v-model="selectedStatus"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="option in props.statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>

                        <div v-if="showMotivoRecusa" class="space-y-2">
                            <Label for="motivo_recusa_pedido">Motivo da recusa *</Label>
                            <textarea
                                id="motivo_recusa_pedido"
                                name="motivo_recusa"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :value="props.documento.motivo_recusa || ''"
                            />
                            <InputError :message="errors.motivo_recusa" />
                        </div>

                        <div class="space-y-2">
                            <Label for="anexo_resposta">Anexo de resposta (opcional)</Label>
                            <input
                                id="anexo_resposta"
                                type="file"
                                name="anexo_resposta"
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                class="block w-full text-sm"
                            />
                            <p class="text-xs text-muted-foreground">
                                PDF, JPG ou PNG — máx. 10 MB.
                            </p>
                            <InputError :message="errors.anexo_resposta" />
                        </div>

                        <div
                            v-if="props.documento.anexo_resposta_url"
                            class="text-sm"
                        >
                            Já existe uma resposta anexada:
                            <a
                                :href="props.documento.anexo_resposta_url"
                                target="_blank"
                                class="text-blue-500 hover:underline"
                            >
                                ver arquivo
                            </a>
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit" :disabled="processing">
                                <Save class="mr-2 h-4 w-4" />
                                {{ processing ? 'Salvando...' : 'Salvar atendimento' }}
                            </Button>
                        </div>
                    </Form>
                </Can>
            </div>

            <!-- Step 3 Atestado: Notificar -->
            <div
                v-if="isAtestado && activeStep === 3"
                class="rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="mb-5 flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300"
                    >
                        <Bell class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold">3. Notificar professores</h3>
                        <p class="text-sm text-muted-foreground">
                            Avise a turma que
                            {{ props.documento.aluno?.nome || 'o aluno' }} está de atestado.
                            Eles recebem um recado no app (e push, se ativo).
                        </p>
                    </div>
                </div>

                <div
                    v-if="!step3Unlocked"
                    class="flex items-start gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                >
                    <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Esta etapa libera depois que o atestado for
                        <strong class="text-foreground">aprovado</strong>. Volte ao passo 2
                        para decidir.
                    </p>
                </div>

                <Can v-else permission="escola.documentos.editar">
                    <div
                        v-if="professoresJaNotificados"
                        class="mb-4 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100"
                    >
                        <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
                        <div class="space-y-1">
                            <p class="font-medium">Professores já notificados</p>
                            <p>
                                Enviado em
                                {{ formatDateTime(props.documento.professores_notificados_em) }}
                                <template
                                    v-if="props.documento.professores_notificados_por"
                                >
                                    por
                                    {{
                                        props.documento.professores_notificados_por
                                            .nome_completo
                                    }}
                                </template>
                                .
                                <template
                                    v-if="professoresNotificadosIds.length > 0"
                                >
                                    {{ professoresNotificadosIds.length }}
                                    professor(es) avisado(s).
                                </template>
                            </p>
                            <p class="text-emerald-800/80 dark:text-emerald-200/80">
                                Você pode enviar novamente se precisar avisar outros
                                professores.
                            </p>
                        </div>
                    </div>

                    <Form
                        v-if="props.professores.length > 0"
                        :action="`/school/documentos/${props.documento.id}/notificar-professores`"
                        method="post"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            v-for="professorId in selectedProfessorIds"
                            :key="professorId"
                            type="hidden"
                            name="professor_ids[]"
                            :value="professorId"
                        />

                        <div class="flex items-center justify-between gap-2">
                            <Label>Professores da turma</Label>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="toggleAllProfessores"
                            >
                                {{
                                    allProfessoresSelected
                                        ? 'Limpar seleção'
                                        : 'Selecionar todos'
                                }}
                            </Button>
                        </div>

                        <div class="max-h-64 space-y-1 overflow-y-auto rounded-md border p-3">
                            <label
                                v-for="professor in props.professores"
                                :key="professor.id"
                                class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted/50"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1 rounded border-input"
                                    :checked="selectedProfessorIds.includes(professor.id)"
                                    @change="toggleProfessor(professor.id)"
                                />
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium">{{ professor.nome }}</span>
                                        <Badge
                                            v-if="professorJaNotificado(professor.id)"
                                            variant="outline"
                                            class="border-emerald-300 text-emerald-700 dark:border-emerald-700 dark:text-emerald-300"
                                        >
                                            Já notificado
                                        </Badge>
                                    </span>
                                    <span
                                        v-if="professor.disciplinas.length > 0"
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                    >
                                        {{ professor.disciplinas.join(', ') }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <InputError :message="errors.professor_ids" />
                        <InputError :message="errors.documento" />

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="processing || selectedProfessorIds.length === 0"
                                class="gap-2"
                            >
                                <Bell class="h-4 w-4" />
                                {{
                                    processing
                                        ? 'Notificando...'
                                        : professoresJaNotificados
                                          ? `Notificar novamente (${selectedProfessorIds.length})`
                                          : `Notificar selecionados (${selectedProfessorIds.length})`
                                }}
                            </Button>
                        </div>
                    </Form>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum professor vinculado à turma ativa deste aluno.
                    </p>
                </Can>
            </div>

            <!-- Documento da escola (somente leitura) -->
            <div
                v-if="!props.documento.pode_analisar"
                class="rounded-xl border bg-card p-6 shadow-sm"
            >
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Circle class="h-4 w-4" />
                    Documento enviado pela escola — sem fluxo de análise.
                </div>
                <div v-if="props.documento.anexo_url" class="mt-4">
                    <a
                        :href="props.documento.anexo_url"
                        target="_blank"
                        class="text-blue-500 hover:underline"
                    >
                        Ver anexo
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
