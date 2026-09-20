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
import { CalendarDays, CreditCard, Eye, Plus, Trash2 } from 'lucide-vue-next';
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

interface Evento {
    id: string;
    titulo: string;
    valor: string | number;
    vencimento_formatado: string | null;
    publico_label: string;
    status: string;
    status_label: string;
    turma: { id: string; nome: string } | null;
    cobrancas_count: number;
    created_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    eventos: Paginated<Evento>;
    filters: {
        search?: string | null;
        status?: string | null;
    };
    statuses: Option[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Financeiro', href: '/school/cobrancas' },
    { title: 'Eventos', href: '/school/cobrancas/eventos' },
];

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

const hasAnyFilter = computed(() => !!search.value || status.value !== '');

function applyFilters() {
    router.get(
        '/school/cobrancas/eventos',
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    status.value = '';
    applyFilters();
}

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

function deleteEvento(id: string) {
    if (confirm('Tem certeza que deseja excluir este evento?')) {
        router.delete(`/school/cobrancas/eventos/${id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Eventos financeiros" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Eventos financeiros"
                        description="Festas, uniformes e outras cobranças pontuais"
                        :icon="CalendarDays"
                    />
                </div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/school/cobrancas" class="flex items-center gap-2">
                            <CreditCard class="h-4 w-4" />
                            Cobranças
                        </Link>
                    </Button>
                    <Can permission="escola.financeiro.criar">
                        <Button as-child>
                            <Link
                                href="/school/cobrancas/eventos/create"
                                class="flex items-center gap-2"
                            >
                                <Plus class="h-4 w-4" />
                                Novo evento
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
                                placeholder="Buscar por título ou descrição..."
                                @keyup.enter="applyFilters"
                            />
                        </div>
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
                                <th class="px-4 py-3">Evento</th>
                                <th class="px-4 py-3">Público</th>
                                <th class="px-4 py-3">Valor</th>
                                <th class="px-4 py-3">Vencimento</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Cobranças</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="evento in props.eventos.data"
                                :key="evento.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ evento.titulo }}</div>
                                    <p class="text-xs text-muted-foreground">
                                        Criado em {{ evento.created_at || '—' }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <div>{{ evento.publico_label }}</div>
                                    <p v-if="evento.turma" class="text-xs text-muted-foreground">
                                        {{ evento.turma.nome }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ formatValor(evento.valor) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ evento.vencimento_formatado || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="getStatusVariant(evento.status)">
                                        {{ evento.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">{{ evento.cobrancas_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button as-child size="sm" variant="ghost" class="hover:bg-transparent">
                                            <Link :href="`/school/cobrancas/eventos/${evento.id}`">
                                                <Eye class="h-4 w-4 text-blue-500 dark:text-blue-400" />
                                            </Link>
                                        </Button>
                                        <Can
                                            v-if="evento.status === 'rascunho'"
                                            permission="escola.financeiro.excluir"
                                        >
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                                @click="deleteEvento(evento.id)"
                                            >
                                                <Trash2 class="h-4 w-4 text-red-500 dark:text-red-400" />
                                            </Button>
                                        </Can>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="props.eventos.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum evento encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.eventos.total }}</span>
                    </p>
                    <Pagination :links="props.eventos.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
