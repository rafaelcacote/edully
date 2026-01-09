<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Edit } from 'lucide-vue-next';

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
    descricao?: string | null;
    carga_horaria_semanal?: number | null;
    ativo: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    disciplina: Disciplina;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Disciplinas',
        href: '/school/disciplinas',
    },
    {
        title: props.disciplina.nome,
        href: `/school/disciplinas/${props.disciplina.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Disciplina: ${props.disciplina.nome}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <BookOpen class="h-5 w-5" />
                            {{ props.disciplina.nome }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes da disciplina
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/disciplinas" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/school/disciplinas/${props.disciplina.id}/edit`" class="flex items-center gap-2">
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
                                <p class="text-sm font-medium text-muted-foreground">Nome</p>
                                <p class="mt-1">{{ props.disciplina.nome }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Sigla</p>
                                <p class="mt-1">{{ props.disciplina.sigla || '—' }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Descrição</p>
                                <p class="mt-1">{{ props.disciplina.descricao || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Carga horária semanal</p>
                                <p class="mt-1">
                                    {{ props.disciplina.carga_horaria_semanal ? `${props.disciplina.carga_horaria_semanal} horas` : '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.disciplina.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ props.disciplina.ativo ? 'Ativa' : 'Inativa' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


