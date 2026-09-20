<script setup lang="ts">
import Can from '@/components/Can.vue';
import DeleteDocumentoDialog from '@/components/documentos/DeleteDocumentoDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, FileText, FolderOpen, Plus } from 'lucide-vue-next';
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

interface Documento {
    id: string;
    titulo: string;
    tipo: string;
    tipo_label: string;
    status: string;
    status_label: string;
    precisa_atencao: boolean;
    aluno: { id: string; nome: string } | null;
    data_inicio: string | null;
    data_fim: string | null;
    anexo_url: string | null;
    created_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    documentos: Paginated<Documento>;
    filters: {
        search?: string | null;
        tipo?: string | null;
        status?: string | null;
    };
    tipos: Option[];
    statuses: Option[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documentos',
        href: '/school/documentos',
    },
];

const search = ref(props.filters.search ?? '');
const tipo = ref(props.filters.tipo ?? '');
const status = ref(props.filters.status ?? '');

const hasAnyFilter = computed(
    () => !!search.value || tipo.value !== '' || status.value !== '',
);

function applyFilters() {
    router.get(
        '/school/documentos',
        {
            search: search.value || undefined,
            tipo: tipo.value || undefined,
            status: status.value || undefined,
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
    applyFilters();
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Documentos" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Documentos"
                        description="Atestados, pedidos de declaração e envios da escola"
                        :icon="FolderOpen"
                    />
                </div>

                <div class="mt-2">
                    <Can permission="escola.documentos.criar">
                        <Button as-child>
                            <Link href="/school/documentos/create" class="flex items-center gap-2">
                                <Plus class="h-4 w-4" />
                                Enviar documento
                            </Link>
                        </Button>
                    </Can>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <Input
                                v-model="search"
                                placeholder="Buscar por título, descrição ou aluno..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="tipo"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-52"
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
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
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

                    <div class="flex items-center gap-2">
                        <Button variant="secondary" @click="applyFilters">Filtrar</Button>
                        <Button v-if="hasAnyFilter" variant="ghost" @click="clearFilters">
                            Limpar
                        </Button>
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
                                <th class="px-4 py-3">Título</th>
                                <th class="px-4 py-3">Aluno</th>
                                <th class="px-4 py-3">Tipo</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Enviado em</th>
                                <th class="px-4 py-3">Anexo</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="documento in props.documentos.data"
                                :key="documento.id"
                                class="border-b last:border-0"
                                :class="
                                    documento.precisa_atencao
                                        ? 'border-l-4 border-l-amber-500 bg-amber-50/70 dark:bg-amber-950/30'
                                        : ''
                                "
                            >
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-medium">{{ documento.titulo }}</div>
                                        <Badge
                                            v-if="documento.precisa_atencao"
                                            class="border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
                                            variant="outline"
                                        >
                                            Atenção
                                        </Badge>
                                    </div>
                                    <p
                                        v-if="documento.data_inicio || documento.data_fim"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ documento.data_inicio || '—' }} —
                                        {{ documento.data_fim || '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    {{ documento.aluno?.nome || '—' }}
                                </td>
                                <td class="px-4 py-3">{{ documento.tipo_label }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="getStatusVariant(documento.status)">
                                        {{ documento.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">{{ documento.created_at || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div
                                        v-if="documento.anexo_url"
                                        class="flex items-center justify-center"
                                    >
                                        <a
                                            :href="documento.anexo_url"
                                            target="_blank"
                                            class="flex items-center gap-1 rounded p-1 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-950 dark:hover:text-blue-400"
                                            title="Visualizar anexo"
                                        >
                                            <FileText class="h-4 w-4" />
                                        </a>
                                    </div>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Can permission="escola.documentos.visualizar">
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                            >
                                                <Link :href="`/school/documentos/${documento.id}`">
                                                    <Eye
                                                        class="h-4 w-4 text-blue-500 dark:text-blue-400"
                                                    />
                                                </Link>
                                            </Button>
                                        </Can>
                                        <Can permission="escola.documentos.excluir">
                                            <DeleteDocumentoDialog :documento="documento" />
                                        </Can>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.documentos.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum documento encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.documentos.total }}</span>
                    </p>
                    <Pagination :links="props.documentos.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
