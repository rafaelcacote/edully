<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Edit, ExternalLink } from 'lucide-vue-next';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    ano_letivo?: number | string | null;
}

interface Professor {
    id: string;
    usuario?: {
        nome_completo: string;
    } | null;
}

interface Exercise {
    id: string;
    disciplina: string;
    titulo: string;
    descricao?: string | null;
    data_entrega: string;
    data_entrega_formatted: string;
    anexo_url?: string | null;
    tipo_exercicio: string;
    turma?: Turma | null;
    professor?: Professor | null;
}

interface Props {
    exercise: Exercise;
}

const props = defineProps<Props>();

const getTipoExercicioLabel = (tipo: string): string => {
    const labels: Record<string, string> = {
        exercicio_caderno: 'Exercício de Caderno',
        exercicio_livro: 'Exercício de Livro',
        trabalho: 'Trabalho',
    };

    return labels[tipo] || tipo;
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Exercícios',
        href: '/school/exercises',
    },
    {
        title: props.exercise.titulo,
        href: `/school/exercises/${props.exercise.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Exercício: ${props.exercise.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <BookOpen class="h-5 w-5" />
                            {{ props.exercise.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes do exercício
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/exercises" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/school/exercises/${props.exercise.id}/edit`" class="flex items-center gap-2">
                            <Edit class="h-4 w-4" />
                            Editar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Informações Básicas</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Título</p>
                                <p class="mt-1">{{ props.exercise.titulo }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Disciplina</p>
                                <p class="mt-1">{{ props.exercise.disciplina }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Turma</p>
                                <p class="mt-1">
                                    {{ props.exercise.turma?.nome || '—' }}
                                    <template
                                        v-if="props.exercise.turma?.serie || props.exercise.turma?.ano_letivo"
                                    >
                                        ({{ [props.exercise.turma.serie, props.exercise.turma.ano_letivo].filter(Boolean).join(' - ') }})
                                    </template>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Data de Entrega</p>
                                <p class="mt-1">{{ props.exercise.data_entrega_formatted }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Tipo de Exercício</p>
                                <p class="mt-1">{{ getTipoExercicioLabel(props.exercise.tipo_exercicio) }}</p>
                            </div>
                            <div v-if="props.exercise.professor">
                                <p class="text-sm font-medium text-muted-foreground">Professor</p>
                                <p class="mt-1">
                                    {{ props.exercise.professor.usuario?.nome_completo || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.exercise.descricao">
                        <h3 class="mb-4 text-lg font-semibold">Descrição</h3>
                        <div class="rounded-md bg-muted/50 p-4">
                            <p class="whitespace-pre-wrap text-sm">
                                {{ props.exercise.descricao }}
                            </p>
                        </div>
                    </div>

                    <div v-if="props.exercise.anexo_url">
                        <h3 class="mb-4 text-lg font-semibold">Anexo</h3>
                        <Button
                            variant="outline"
                            as-child
                            class="flex items-center gap-2"
                        >
                            <a
                                :href="props.exercise.anexo_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-2"
                            >
                                <ExternalLink class="h-4 w-4" />
                                Abrir anexo
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

