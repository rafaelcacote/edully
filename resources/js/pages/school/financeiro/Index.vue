<script setup lang="ts">
import Can from '@/components/Can.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CreditCard, Eye, FileText, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Cobranca {
    id: string;
    titulo: string;
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
    tem_pix: boolean;
}

interface Option {
    value: string;
    label: string;
}

interface NamedOption {
    id: string;
    nome: string;
}

interface Props {
    cobrancas: Paginated<Cobranca>;
    filters: {
        search?: string | null;
        tipo?: string | null;
        status?: string | null;
        turma_id?: string | null;
        aluno_id?: string | null;
        referencia?: string | null;
    };
    alunos: NamedOption[];
    turmas: NamedOption[];
    tipos: Option[];
    statuses: Option[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Financeiro',
        href: '/school/cobrancas',
    },
];

const search = ref(props.filters.search ?? '');
const tipo = ref(props.filters.tipo ?? '');
const status = ref(props.filters.status ?? '');
const turmaId = ref(props.filters.turma_id ?? '');
const alunoId = ref(props.filters.aluno_id ?? '');
const referencia = ref(props.filters.referencia ?? '');

const hasAnyFilter = computed(
    () =>
        !!search.value ||
        tipo.value !== '' ||
        status.value !== '' ||
        turmaId.value !== '' ||
        alunoId.value !== '' ||
        referencia.value !== '',
);

function applyFilters() {
    router.get(
        '/school/cobrancas',
        {
            search: search.value || undefined,
            tipo: tipo.value || undefined,
            status: status.value || undefined,
            turma_id: turmaId.value || undefined,
            aluno_id: alunoId.value || undefined,
            referencia: referencia.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function clearFilters() {
    search.value = '';
    tipo.value = '';
    status.value = '';
    turmaId.value = '';
    alunoId.value = '';
    referencia.value = '';
    applyFilters();
}

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

function deleteCobranca(id: string) {
    if (confirm('Tem certeza que deseja excluir esta cobrança?')) {
        router.delete(`/school/cobrancas/${id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Financeiro" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Financeiro"
                        description="Mensalidades e cobranças dos alunos"
                        :icon="CreditCard"
                    />
                </div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/school/cobrancas/eventos" class="flex items-center gap-2">
                            Eventos
                        </Link>
                    </Button>
                    <Can permission="escola.financeiro.criar">
                        <Button as-child>
                            <Link
                                href="/school/cobrancas/mensalidades/gerar"
                                class="flex items-center gap-2"
                            >
                                <Plus class="h-4 w-4" />
                                Gerar mensalidades
                            </Link>
                        </Button>
                    </Can>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-3 lg:flex-row">
                        <div class="flex-1">
                            <Input
                                v-model="search"
                                placeholder="Buscar por título, aluno ou referência..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <Input
                            v-model="referencia"
                            placeholder="Ref. (ex: 2026-03)"
                            class="lg:w-40"
                            @keyup.enter="applyFilters"
                        />

                        <select
                            v-model="tipo"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm lg:w-44"
                            @change="applyFilters"
                        >
                            <option value="">Todos os tipos</option>
                            <option
                                v-for="option in props.tipos"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <select
                            v-model="status"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm lg:w-40"
                            @change="applyFilters"
                        >
                            <option value="">Todos status</option>
                            <option
                                v-for="option in props.statuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                            <select
                                v-model="turmaId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-52"
                                @change="applyFilters"
                            >
                                <option value="">Todas as turmas</option>
                                <option
                                    v-for="turma in props.turmas"
                                    :key="turma.id"
                                    :value="turma.id"
                                >
                                    {{ turma.nome }}
                                </option>
                            </select>

                            <select
                                v-model="alunoId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-56"
                                @change="applyFilters"
                            >
                                <option value="">Todos os alunos</option>
                                <option
                                    v-for="aluno in props.alunos"
                                    :key="aluno.id"
                                    :value="aluno.id"
                                >
                                    {{ aluno.nome }}
                                </option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button variant="secondary" @click="applyFilters">Filtrar</Button>
                            <Button v-if="hasAnyFilter" variant="ghost" @click="clearFilters">
                                Limpar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500 dark:bg-neutral-900/40 dark:text-neutral-400"
                        >
                            <tr>
                                <th class="px-4 py-3">Cobrança</th>
                                <th class="px-4 py-3">Aluno</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Pagamento</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="cobranca in props.cobrancas.data"
                                :key="cobranca.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ cobranca.titulo }}</div>
                                    <p
                                        v-if="cobranca.referencia"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Ref. {{ cobranca.referencia }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ cobranca.aluno?.nome || '—' }}
                                </td>
                                <td class="px-4 py-3">{{ cobranca.tipo_label }}</td>
                                <td class="px-4 py-3 font-medium">
                                    {{ formatValor(cobranca.valor) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ cobranca.vencimento_formatado || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="getStatusVariant(cobranca.status_exibicao)">
                                        {{ cobranca.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 text-muted-foreground">
                                        <a
                                            v-if="cobranca.boleto_url"
                                            :href="cobranca.boleto_url"
                                            target="_blank"
                                            class="rounded p-1 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-950 dark:hover:text-blue-400"
                                            title="Abrir boleto"
                                        >
                                            <FileText class="h-4 w-4" />
                                        </a>
                                        <span v-if="cobranca.tem_pix" class="text-xs">PIX</span>
                                        <span
                                            v-if="!cobranca.boleto_url && !cobranca.tem_pix"
                                            class="text-xs"
                                        >
                                            —
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Can permission="escola.financeiro.visualizar">
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                            >
                                                <Link :href="`/school/cobrancas/${cobranca.id}`">
                                                    <Eye
                                                        class="h-4 w-4 text-blue-500 dark:text-blue-400"
                                                    />
                                                </Link>
                                            </Button>
                                        </Can>
                                        <Can permission="escola.financeiro.excluir">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                                @click="deleteCobranca(cobranca.id)"
                                            >
                                                <Trash2
                                                    class="h-4 w-4 text-red-500 dark:text-red-400"
                                                />
                                            </Button>
                                        </Can>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.cobrancas.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhuma cobrança encontrada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.cobrancas.total }}</span>
                    </p>
                    <Pagination :links="props.cobrancas.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
