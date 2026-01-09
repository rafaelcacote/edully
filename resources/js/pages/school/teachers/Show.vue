<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, UserCheck } from 'lucide-vue-next';

interface Teacher {
    id: string;
    matricula: string;
    disciplinas?: string[] | null;
    especializacao?: string | null;
    ativo: boolean;
    nome_completo?: string;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
}

interface Props {
    teacher: Teacher;
}

const props = defineProps<Props>();

function formatPhone(phone: string | null | undefined): string {
    if (!phone) return '—';
    const numbers = phone.replace(/\D/g, '');
    if (numbers.length === 10) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    } else if (numbers.length === 11) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7)}`;
    }
    return phone;
}

function formatCPF(cpf: string | null | undefined): string {
    if (!cpf) return '—';
    const numbers = cpf.replace(/\D/g, '');
    if (numbers.length === 11) {
        return `${numbers.slice(0, 3)}.${numbers.slice(3, 6)}.${numbers.slice(6, 9)}-${numbers.slice(9, 11)}`;
    }
    return cpf;
}


const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Professores',
        href: '/school/teachers',
    },
    {
        title: props.teacher.nome_completo || props.teacher.matricula,
        href: `/school/teachers/${props.teacher.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Professor: ${props.teacher.nome_completo || props.teacher.matricula}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <UserCheck class="h-5 w-5" />
                            {{ props.teacher.nome_completo || props.teacher.matricula }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Matrícula: {{ props.teacher.matricula }}
                        </p>
                    </div>
                </div>

                <Button
                    variant="outline"
                    as-child
                    class="rounded-lg"
                >
                    <Link href="/school/teachers" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Dados Pessoais</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nome completo</p>
                                <p class="mt-1">{{ props.teacher.nome_completo || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">CPF</p>
                                <p class="mt-1">{{ formatCPF(props.teacher.cpf) }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">E-mail</p>
                                <p class="mt-1">{{ props.teacher.email || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Telefone</p>
                                <p class="mt-1">{{ formatPhone(props.teacher.telefone) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="mb-4 text-lg font-semibold">Dados Profissionais</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Matrícula</p>
                                <p class="mt-1 font-medium">{{ props.teacher.matricula }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.teacher.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ props.teacher.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </div>
                            </div>
                            <div v-if="props.teacher.especializacao" class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Especialização</p>
                                <p class="mt-1">{{ props.teacher.especializacao }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="mb-4 text-lg font-semibold">Disciplinas</h3>
                        <div v-if="props.teacher.disciplinas && props.teacher.disciplinas.length > 0" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="(disciplina, index) in props.teacher.disciplinas"
                                :key="index"
                                variant="secondary"
                                class="px-3 py-1.5 text-sm"
                            >
                                {{ disciplina }}
                            </Badge>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            Nenhuma disciplina vinculada a este professor.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

