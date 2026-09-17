<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Edit } from 'lucide-vue-next';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: number | string | null;
}

interface Professor {
    id: string;
    usuario?: {
        nome_completo: string;
    } | null;
}

interface Test {
    id: string;
    disciplina: string;
    titulo: string;
    descricao?: string | null;
    bimestre?: number | null;
    data_prova: string;
    data_prova_formatted: string;
    horario?: string | null;
    horario_formatted?: string | null;
    sala?: string | null;
    duracao_minutos?: number | null;
    turma?: Turma | null;
    professor?: Professor | null;
}

interface Props {
    test: Test;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Provas',
        href: '/school/tests',
    },
    {
        title: props.test.titulo,
        href: `/school/tests/${props.test.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Prova: ${props.test.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <ClipboardCheck class="h-5 w-5" />
                            {{ props.test.titulo }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes da prova
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/tests" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/school/tests/${props.test.id}/edit`" class="flex items-center gap-2">
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
                                <p class="mt-1">{{ props.test.titulo }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Disciplina</p>
                                <p class="mt-1">{{ props.test.disciplina }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Turma</p>
                                <p class="mt-1">
                                    <template v-if="props.test.turma">
                                        {{ props.test.turma.nome }}<span v-if="props.test.turma.turma_letra"> {{ props.test.turma.turma_letra }}</span>
                                        <template
                                            v-if="props.test.turma.serie || props.test.turma.ano_letivo"
                                        >
                                            ({{ [props.test.turma.serie, props.test.turma.ano_letivo].filter(Boolean).join(' - ') }})
                                        </template>
                                    </template>
                                    <template v-else>—</template>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Data da Prova</p>
                                <p class="mt-1">{{ props.test.data_prova_formatted }}</p>
                            </div>
                            <div v-if="props.test.bimestre">
                                <p class="text-sm font-medium text-muted-foreground">Bimestre</p>
                                <p class="mt-1">
                                    <Badge variant="secondary">{{ props.test.bimestre }}º Bimestre</Badge>
                                </p>
                            </div>
                            <div v-if="props.test.horario_formatted">
                                <p class="text-sm font-medium text-muted-foreground">Horário</p>
                                <p class="mt-1">{{ props.test.horario_formatted }}</p>
                            </div>
                            <div v-if="props.test.sala">
                                <p class="text-sm font-medium text-muted-foreground">Sala</p>
                                <p class="mt-1">{{ props.test.sala }}</p>
                            </div>
                            <div v-if="props.test.duracao_minutos">
                                <p class="text-sm font-medium text-muted-foreground">Duração</p>
                                <p class="mt-1">{{ props.test.duracao_minutos }} minutos</p>
                            </div>
                            <div v-if="props.test.professor">
                                <p class="text-sm font-medium text-muted-foreground">Professor</p>
                                <p class="mt-1">
                                    {{ props.test.professor.usuario?.nome_completo || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.test.descricao">
                        <h3 class="mb-4 text-lg font-semibold">Descrição</h3>
                        <div class="rounded-md bg-muted/50 p-4">
                            <p class="whitespace-pre-wrap text-sm">
                                {{ props.test.descricao }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

