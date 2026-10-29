<script setup lang="ts">
import ActionTooltip from '@/components/ActionTooltip.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { create, edit, index } from '@/routes/plans';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Plus, CreditCard } from 'lucide-vue-next';
import { computed, ref } from 'vue';

function formatPrice(price: number | null | undefined): string {
    if (!price && price !== 0) return '—';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(price);
}

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

interface Plan {
    id: string;
    nome: string;
    descricao?: string;
    preco_mensal: number;
    preco_anual?: number;
    ativo: boolean;
    max_alunos?: number;
    max_professores?: number;
    max_armazenamento_mb?: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    plans: Paginated<Plan>;
    filters: {
        search?: string | null;
        active?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Planos',
        href: index().url,
    },
];

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active ?? '');

const hasAnyFilter = computed(() => !!search.value || active.value !== '');

function applyFilters() {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            active: active.value || undefined,
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
    active.value = '';
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Planos" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Planos"
                        description="Gerencie os planos disponíveis"
                        :icon="CreditCard"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link :href="create()" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo plano
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-4 shadow-sm">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                        <div class="flex-1">
                            <Input
                                v-model="search"
                                placeholder="Buscar por nome ou descrição..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="active"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                            @change="applyFilters"
                        >
                            <option value="">Todos status</option>
                            <option value="true">Ativos</option>
                            <option value="false">Inativos</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button variant="secondary" @click="applyFilters">
                            Filtrar
                        </Button>
                        <Button
                            v-if="hasAnyFilter"
                            variant="ghost"
                            @click="clearFilters"
                        >
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
                                <th class="px-4 py-3">Nome</th>
                                <th class="px-4 py-3">Preço Mensal</th>
                                <th class="px-4 py-3">Preço Anual</th>
                                <th class="px-4 py-3">Limites</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="plan in props.plans.data"
                                :key="plan.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ plan.nome }}</div>
                                    <div
                                        v-if="plan.descricao"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ plan.descricao }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatPrice(plan.preco_mensal) }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ formatPrice(plan.preco_anual) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-muted-foreground">
                                        <div v-if="plan.max_alunos !== null && plan.max_alunos !== undefined">
                                            Alunos: {{ plan.max_alunos || 'Ilimitado' }}
                                        </div>
                                        <div v-if="plan.max_professores !== null && plan.max_professores !== undefined">
                                            Professores: {{ plan.max_professores || 'Ilimitado' }}
                                        </div>
                                        <div v-if="plan.max_armazenamento_mb !== null && plan.max_armazenamento_mb !== undefined">
                                            Armazenamento: {{ plan.max_armazenamento_mb ? `${plan.max_armazenamento_mb} MB` : 'Ilimitado' }}
                                        </div>
                                        <div v-if="(!plan.max_alunos && !plan.max_professores && !plan.max_armazenamento_mb)">
                                            Ilimitado
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="plan.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ plan.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <ActionTooltip label="Editar">
                                            <Button
                                                as-child
                                                size="sm"
                                                variant="ghost"
                                                class="hover:bg-transparent"
                                            >
                                                <Link :href="edit({ plan: plan.id })">
                                                    <Edit
                                                        class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                    />
                                                </Link>
                                            </Button>
                                        </ActionTooltip>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.plans.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum plano encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.plans.total }}</span>
                    </p>
                    <Pagination :links="props.plans.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

