<script setup lang="ts">
import DeleteMessageDialog from '@/components/messages/DeleteMessageDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageSquare, Edit, Eye, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Aluno {
    id: string;
    nome: string;
    nome_social?: string | null;
}

interface Message {
    id: string;
    titulo: string;
    aluno: Aluno | null;
    tipo?: string | null;
    prioridade?: string | null;
    lida: boolean;
    created_at: string;
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
    messages: Paginated<Message>;
    alunos: Array<{
        id: string;
        nome: string;
    }>;
    turmas: Array<{
        id: string;
        nome: string;
    }>;
    filters: {
        search?: string | null;
        aluno_id?: string | null;
        turma_id?: string | null;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: '/school/messages',
    },
];

const search = ref(props.filters.search ?? '');
const alunoId = ref(props.filters.aluno_id ?? '');
const turmaId = ref(props.filters.turma_id ?? '');

const hasAnyFilter = computed(
    () => !!search.value || alunoId.value !== '' || turmaId.value !== '',
);

function applyFilters() {
    router.get(
        '/school/messages',
        {
            search: search.value || undefined,
            aluno_id: alunoId.value || undefined,
            turma_id: turmaId.value || undefined,
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
    alunoId.value = '';
    turmaId.value = '';
    applyFilters();
}

function getPrioridadeBadgeClass(prioridade?: string | null): string {
    switch (prioridade) {
        case 'alta':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'media':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'normal':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        default:
            return 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-400';
    }
}

function getPrioridadeLabel(prioridade?: string | null): string {
    switch (prioridade) {
        case 'alta':
            return 'Alta';
        case 'media':
            return 'Média';
        case 'normal':
            return 'Normal';
        default:
            return 'Normal';
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Mensagens" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Mensagens"
                        description="Gerencie as mensagens enviadas"
                        :icon="MessageSquare"
                    />
                </div>

                <div class="mt-2">
                    <Button as-child>
                        <Link href="/school/messages/create" class="flex items-center gap-2">
                            <Plus class="h-4 w-4" />
                            Nova mensagem
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
                                placeholder="Buscar por título, conteúdo ou aluno..."
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <select
                            v-model="turmaId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                            @change="applyFilters"
                        >
                            <option value="">Todas as turmas</option>
                            <option
                                v-for="turma in turmas"
                                :key="turma.id"
                                :value="turma.id"
                            >
                                {{ turma.nome }}
                            </option>
                        </select>

                        <select
                            v-model="alunoId"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-48"
                            @change="applyFilters"
                        >
                            <option value="">Todos os alunos</option>
                            <option
                                v-for="aluno in alunos"
                                :key="aluno.id"
                                :value="aluno.id"
                            >
                                {{ aluno.nome }}
                            </option>
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
                                <th class="px-4 py-3">Aluno</th>
                                <th class="px-4 py-3">Prioridade</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Data</th>
                                <th class="px-4 py-3 text-center">Ações</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="message in props.messages.data"
                                :key="message.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{ message.titulo }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ message.aluno?.nome || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="getPrioridadeBadgeClass(message.prioridade)"
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    >
                                        {{ getPrioridadeLabel(message.prioridade) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="message.lida"
                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        Lida
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        Não lida
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ message.created_at }}</td>
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
                                            <Link :href="`/school/messages/${message.id}`">
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
                                            <Link :href="`/school/messages/${message.id}/edit`">
                                                <Edit
                                                    class="h-4 w-4 text-amber-500 dark:text-amber-400"
                                                />
                                            </Link>
                                        </Button>
                                        <DeleteMessageDialog :message="message" />
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="props.messages.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-sm text-muted-foreground"
                                >
                                    Nenhuma mensagem encontrada.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 border-t p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Total: <span class="font-medium">{{ props.messages.total }}</span>
                    </p>
                    <Pagination :links="props.messages.links" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
