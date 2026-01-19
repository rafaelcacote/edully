<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, Edit, Eye, FileText, Plus, Trash2 } from 'lucide-vue-next';
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

interface Aviso {
    id: string;
    titulo: string;
    prioridade: string;
    publico_alvo: string;
    publicado: boolean;
    publicado_em: string | null;
    expira_em: string | null;
    anexo_url: string | null;
    created_at: string;
}

interface Props {
    avisos: Paginated<Aviso>;
    filters: {
        search?: string | null;
        published?: string | null;
        priority?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Avisos',
        href: '/school/avisos',
    },
];

const search = ref(props.filters.search ?? '');
const published = ref(props.filters.published ?? '');
const priority = ref(props.filters.priority ?? '');

const hasAnyFilter = computed(
    () => !!search.value || published.value !== '' || priority.value !== '',
);

function applyFilters() {
    router.get(
        '/school/avisos',
        {
            search: search.value || undefined,
            published: published.value || undefined,
            priority: priority.value || undefined,
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
    published.value = '';
    priority.value = '';
    applyFilters();
}

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

function deleteAviso(avisoId: string) {
    if (confirm('Tem certeza que deseja excluir este aviso?')) {
        router.delete(`/school/avisos/${avisoId}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Avisos" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Avisos"
                        description="Gerencie os avisos da escola"
                        :icon="Bell"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/avisos/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Novo aviso
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
                                placeholder="Buscar por título ou conteúdo..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="published"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                            @change="applyFilters"
                        >
                            <option value="">Todos status</option>
                            <option value="true">Publicados</option>
                            <option value="false">Não publicados</option>
                        </select>

                        <select
                            v-model="priority"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-44"
                            @change="applyFilters"
                        >
                            <option value="">Todas prioridades</option>
                            <option value="normal">Normal</option>
                            <option value="alta">Alta</option>
                            <option value="media">Média</option>
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
                                <th class="px-4 py-3">Título</th>
                                <th class="px-4 py-3">Prioridade</th>
                                <th class="px-4 py-3">Público-alvo</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Publicado em</th>
                                <th class="px-4 py-3">Anexo</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="aviso in props.avisos.data"
                                :key="aviso.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ aviso.titulo }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            getPrioridadeVariant(aviso.prioridade)
                                        "
                                    >
                                        {{ getPrioridadeLabel(aviso.prioridade) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{ getPublicoAlvoLabel(aviso.publico_alvo) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="aviso.publicado ? 'default' : 'secondary'"
                                    >
                                        {{ aviso.publicado ? 'Publicado' : 'Não publicado' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        aviso.publicado_em
                                            ? new Date(aviso.publicado_em).toLocaleString('pt-BR')
                                            : '—'
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="aviso.anexo_url" class="flex items-center justify-center">
                                        <a
                                            :href="aviso.anexo_url"
                                            target="_blank"
                                            class="flex items-center gap-1 rounded p-1 text-blue-500 transition-colors hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-950 dark:hover:text-blue-400"
                                            title="Visualizar PDF"
                                        >
                                            <FileText class="h-4 w-4" />
                                        </a>
                                    </div>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button
                                            as-child
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                        >
                                            <Link :href="`/school/avisos/${aviso.id}`">
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
                                            <Link :href="`/school/avisos/${aviso.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="hover:bg-transparent"
                                            @click="deleteAviso(aviso.id)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-red-500 dark:text-red-400"
                                            />
                                        </Button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.avisos.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhum aviso encontrado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.avisos.total }}</span>
                    </p>
                    <Pagination :links="props.avisos.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
