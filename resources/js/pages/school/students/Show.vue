<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, GraduationCap, RefreshCw, User } from 'lucide-vue-next';
import { ref } from 'vue';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: string | null;
}

interface Student {
    id: string;
    nome: string;
    nome_social?: string | null;
    foto_url?: string | null;
    data_nascimento?: string | null;
    ativo: boolean;
    informacoes_medicas?: string | null;
    turma?: Turma | null;
    parents?: Array<{
        id: string;
        nome_completo: string;
        parentesco?: string | null;
    }>;
}

interface Props {
    student: Student;
    turmas?: Turma[];
}

const props = defineProps<Props>();
const reenrollDialogOpen = ref(false);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Alunos',
        href: '/school/students',
    },
    {
        title: props.student.nome,
        href: `/school/students/${props.student.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Aluno: ${props.student.nome}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2 flex items-start gap-4">
                    <div
                        class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-input bg-muted"
                    >
                        <img
                            v-if="props.student.foto_url"
                            :src="props.student.foto_url"
                            :alt="`Foto de ${props.student.nome}`"
                            class="h-full w-full object-cover"
                        />
                        <User v-else class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <GraduationCap class="h-5 w-5" />
                            {{ props.student.nome }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Perfil do aluno
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/students" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/school/students/${props.student.id}/edit`" class="flex items-center gap-2">
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
                            <div class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Foto</p>
                                <div class="mt-2">
                                    <img
                                        v-if="props.student.foto_url"
                                        :src="props.student.foto_url"
                                        :alt="`Foto de ${props.student.nome}`"
                                        class="h-40 w-40 rounded-lg border border-input object-cover"
                                    />
                                    <span v-else class="text-sm text-muted-foreground">Sem foto cadastrada</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nome completo</p>
                                <p class="mt-1">{{ props.student.nome }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nome social</p>
                                <p class="mt-1">{{ props.student.nome_social || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Turma atual</p>
                                <p class="mt-1">
                                    <span v-if="props.student.turma">
                                        {{ props.student.turma.nome }}
                                        <template v-if="props.student.turma.serie || props.student.turma.turma_letra">
                                            ({{ [props.student.turma.serie, props.student.turma.turma_letra].filter(Boolean).join(' - ') }})
                                        </template>
                                        <template v-if="props.student.turma.ano_letivo">
                                            - {{ props.student.turma.ano_letivo }}
                                        </template>
                                    </span>
                                    <span v-else>—</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Data de nascimento</p>
                                <p class="mt-1">
                                    {{ props.student.data_nascimento ? new Date(props.student.data_nascimento).toLocaleDateString('pt-BR') : '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.student.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ props.student.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.student.parents && props.student.parents.length > 0">
                        <h3 class="mb-4 text-lg font-semibold">Responsáveis</h3>
                        <div class="space-y-2">
                            <div
                                v-for="parent in props.student.parents"
                                :key="parent.id"
                                class="rounded-lg border p-3"
                            >
                                <p class="font-medium">{{ parent.nome_completo }}</p>
                                <p v-if="parent.parentesco" class="text-sm text-muted-foreground">
                                    {{ parent.parentesco }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.student.informacoes_medicas">
                        <h3 class="mb-4 text-lg font-semibold">Informações médicas</h3>
                        <p class="text-sm whitespace-pre-wrap">{{ props.student.informacoes_medicas }}</p>
                    </div>
                </div>
            </div>

            <!-- Seção de Rematrícula -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Rematrícula</h3>
                        <p class="text-sm text-muted-foreground">
                            Rematricule este aluno em uma nova turma de um novo ano letivo.
                        </p>
                    </div>

                    <Dialog v-model:open="reenrollDialogOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" class="flex items-center gap-2">
                                <RefreshCw class="h-4 w-4" />
                                Rematricular
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Rematricular aluno</DialogTitle>
                                <DialogDescription>
                                    Selecione a nova turma para rematricular {{ props.student.nome }}.
                                    A matrícula atual será desativada e uma nova será criada.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                :action="`/school/students/${props.student.id}/reenroll`"
                                method="post"
                                @success="reenrollDialogOpen = false"
                                class="mt-4 space-y-6"
                                v-slot="{ processing, errors }"
                            >
                                <div class="grid gap-2">
                                    <Label for="turma_id">Nova turma</Label>
                                    <select
                                        id="turma_id"
                                        name="turma_id"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        required
                                    >
                                        <option value="">Selecione uma turma</option>
                                        <option
                                            v-for="turma in props.turmas"
                                            :key="turma.id"
                                            :value="turma.id"
                                        >
                                            {{ turma.nome }}
                                            <template v-if="turma.serie || turma.turma_letra">
                                                ({{ [turma.serie, turma.turma_letra].filter(Boolean).join(' - ') }})
                                            </template>
                                            <template v-if="turma.ano_letivo">
                                                - {{ turma.ano_letivo }}
                                            </template>
                                        </option>
                                    </select>
                                    <InputError :message="errors.turma_id" />
                                    <p class="text-xs text-muted-foreground">
                                        <strong>Turma atual:</strong>
                                        <span v-if="props.student.turma">
                                            {{ props.student.turma.nome }}
                                            <template v-if="props.student.turma.ano_letivo">
                                                ({{ props.student.turma.ano_letivo }})
                                            </template>
                                        </span>
                                        <span v-else>Nenhuma</span>
                                    </p>
                                </div>

                                <div class="flex items-center justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="reenrollDialogOpen = false"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                                        <RefreshCw class="h-4 w-4" />
                                        {{ processing ? 'Rematriculando...' : 'Confirmar rematrícula' }}
                                    </Button>
                                </div>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

