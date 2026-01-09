<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Edit, Eye, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
    descricao?: string | null;
    carga_horaria_semanal?: number | null;
    ativo: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    disciplinas: Paginated<Disciplina>;
    filters: {
        search?: string | null;
        active?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Disciplinas',
        href: '/school/disciplinas',
    },
];

const search = ref(props.filters.search ?? '');
const active = ref(props.filters.active ?? '');

const updatingStatus = ref<Record<string, boolean>>({});

const hasAnyFilter = computed(
    () => !!search.value || active.value !== '',
);

function applyFilters() {
    router.get(
        '/school/disciplinas',
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

function toggleStatus(disciplinaId: string, nextStatus: boolean) {
    router.patch(
        `/school/disciplinas/${disciplinaId}/toggle-status`,
        { ativo: nextStatus },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                updatingStatus.value = {
                    ...updatingStatus.value,
                    [disciplinaId]: true,
                };
            },
            onFinish: () => {
                updatingStatus.value = {
                    ...updatingStatus.value,
                    [disciplinaId]: false,
                };
            },
        },
    );
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Disciplinas" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Disciplinas"
                        description="Gerencie as disciplinas cadastradas"
                        :icon="BookOpen"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/disciplinas/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Nova disciplina
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
                                placeholder="Buscar por nome, sigla ou descrição..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="active"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                            @change="applyFilters"
                        >
                            <option value="">Todos status</option>
                            <option value="true">Ativas</option>
                            <option value="false">Inativas</option>
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
                                <th class="px-4 py-3">Sigla</th>
                                <th class="px-4 py-3">Carga Horária Semanal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="disciplina in props.disciplinas.data"
                                :key="disciplina.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ disciplina.nome }}
                                    </div>
                                    <div
                                        v-if="disciplina.descricao"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ disciplina.descricao }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ disciplina.sigla || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ disciplina.carga_horaria_semanal ? `${disciplina.carga_horaria_semanal} horas` : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Switch
                                            :model-value="disciplina.ativo"
                                            :disabled="!!updatingStatus[disciplina.id]"
                                            @update:modelValue="(value: boolean) => toggleStatus(disciplina.id, value)"
                                        />
                                        <span
                                            v-if="updatingStatus[disciplina.id]"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Alterando...
                                        </span>
                                        <Badge
                                            :variant="disciplina.ativo ? 'default' : 'destructive'"
                                            class="text-xs"
                                        >
                                            {{ disciplina.ativo ? 'Ativa' : 'Inativa' }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-center gap-2"
                                    >
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/disciplinas/${disciplina.id}`">
                                                <Eye
                                                    class="h-4 w-4 text-blue-500 dark:text-blue-400"
                                                />
                                            </Link>
                                        </Button>
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/disciplinas/${disciplina.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.disciplinas.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhuma disciplina encontrada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.disciplinas.total }}</span>
                    </p>
                    <Pagination :links="props.disciplinas.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>


