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
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import StudentForm from '../students/Partials/StudentForm.vue';

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
    turma?: {
        id: string;
        nome: string;
        serie?: string | null;
        turma_letra?: string | null;
        ano_letivo?: string | null;
    } | null;
}

interface Parent {
    id: string;
    nome_completo?: string | null;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
    parentesco?: string | null;
    profissao?: string | null;
    ativo: boolean;
    students?: Student[];
}

interface Props {
    parent: Parent;
    turmas?: Turma[];
}

const props = defineProps<Props>();
const createDialogOpen = ref(false);
const dialogMode = ref<'create' | 'attach'>('create');
const studentSearch = ref('');
const studentSearchResults = ref<Student[]>([]);
const isSearching = ref(false);
const selectedStudentId = ref<string | null>(null);
const isStudentDropdownOpen = ref(false);
const studentSearchInputRef = ref<HTMLInputElement | null>(null);

interface SearchStudent {
    id: string;
    nome: string;
    nome_social?: string | null;
    foto_url?: string | null;
    ativo: boolean;
}

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
        title: 'Responsáveis',
        href: '/school/parents',
    },
    {
        title: props.parent.nome_completo || 'Responsável',
        href: `/school/parents/${props.parent.id}`,
    },
];

function detachStudent(studentId: string) {
    if (!confirm('Remover o vínculo deste aluno com o responsável?')) {
        return;
    }

    router.delete(`/school/parents/${props.parent.id}/students/${studentId}`, {
        preserveScroll: true,
    });
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function searchStudents() {
    if (!studentSearch.value.trim()) {
        studentSearchResults.value = [];
        isStudentDropdownOpen.value = false;
        return;
    }

    isSearching.value = true;
    isStudentDropdownOpen.value = true;

    try {
        const response = await fetch(`/school/students/search?search=${encodeURIComponent(studentSearch.value)}&limit=20`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Erro ao buscar alunos: ${response.status}`);
        }

        const data = await response.json();
        
        // Filtrar alunos já vinculados
        const linkedStudentIds = props.parent.students?.map(s => s.id) || [];
        studentSearchResults.value = (data.students || []).filter((s: SearchStudent) => !linkedStudentIds.includes(s.id));
    } catch (error) {
        console.error('Erro ao buscar alunos:', error);
        studentSearchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}

watch(studentSearch, (newValue) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (!newValue.trim()) {
        studentSearchResults.value = [];
        isStudentDropdownOpen.value = false;
        selectedStudentId.value = null;
        return;
    }

    searchTimeout = setTimeout(() => {
        searchStudents();
    }, 300);
});

function selectStudent(student: SearchStudent) {
    selectedStudentId.value = student.id;
    studentSearch.value = student.nome;
    isStudentDropdownOpen.value = false;
}

function toggleStudentDropdown() {
    if (!isStudentDropdownOpen.value && studentSearch.value.trim()) {
        isStudentDropdownOpen.value = true;
    }
}

function attachExistingStudent() {
    if (!selectedStudentId.value) {
        return;
    }

    router.post(`/school/parents/${props.parent.id}/students/attach`, {
        student_id: selectedStudentId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            createDialogOpen.value = false;
            dialogMode.value = 'create';
            selectedStudentId.value = null;
            studentSearch.value = '';
            studentSearchResults.value = [];
            isStudentDropdownOpen.value = false;
        },
    });
}

watch(createDialogOpen, (isOpen) => {
    if (!isOpen) {
        // Reset quando o dialog fecha
        if (dialogMode.value === 'attach') {
            selectedStudentId.value = null;
            studentSearch.value = '';
            studentSearchResults.value = [];
            isStudentDropdownOpen.value = false;
        }
    } else if (dialogMode.value === 'attach') {
        // Focar no input quando abrir no modo attach
        setTimeout(() => {
            studentSearchInputRef.value?.focus();
        }, 100);
    }
});

const selectedStudentName = computed(() => {
    if (!selectedStudentId.value || !studentSearchResults.value.length) {
        return '';
    }
    const student = studentSearchResults.value.find(s => s.id === selectedStudentId.value);
    return student?.nome || '';
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Responsável: ${props.parent.nome_completo || 'Sem nome'}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <Users class="h-5 w-5" />
                            {{ props.parent.nome_completo || 'Sem nome' }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ props.parent.parentesco || 'Responsável' }}
                        </p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        as-child
                        class="rounded-lg"
                    >
                        <Link :href="`/school/parents/${props.parent.id}/edit`" class="flex items-center gap-2">
                            Editar
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        as-child
                        class="rounded-lg"
                    >
                        <Link href="/school/parents" class="flex items-center gap-2">
                            <ArrowLeft class="h-4 w-4" />
                            Voltar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Dados Pessoais</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nome completo</p>
                                <p class="mt-1">{{ props.parent.nome_completo || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">CPF</p>
                                <p class="mt-1">{{ formatCPF(props.parent.cpf) }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">E-mail</p>
                                <p class="mt-1">{{ props.parent.email || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Telefone</p>
                                <p class="mt-1">{{ formatPhone(props.parent.telefone) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="mb-4 text-lg font-semibold">Dados do Responsável</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Parentesco</p>
                                <p class="mt-1">{{ props.parent.parentesco || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Profissão</p>
                                <p class="mt-1">{{ props.parent.profissao || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.parent.ativo ? 'default' : 'destructive'"
                                    >
                                        {{ props.parent.ativo ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Alunos vinculados</h3>
                    <p class="text-sm text-muted-foreground">
                        Visualize ou cadastre alunos ligados a este responsável.
                    </p>
                </div>

                <Dialog v-model:open="createDialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            Adicionar aluno
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                {{ dialogMode === 'create' ? 'Novo aluno para este responsável' : 'Vincular aluno existente' }}
                            </DialogTitle>
                            <DialogDescription>
                                {{ dialogMode === 'create' ? 'Crie o aluno e o vínculo será feito automaticamente.' : 'Pesquise e selecione um aluno já cadastrado para vincular ao responsável.' }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="mt-4">
                            <div class="flex gap-2 mb-4">
                                <Button
                                    :variant="dialogMode === 'create' ? 'default' : 'outline'"
                                    @click="dialogMode = 'create'"
                                    class="flex-1"
                                >
                                    Criar novo aluno
                                </Button>
                                <Button
                                    :variant="dialogMode === 'attach' ? 'default' : 'outline'"
                                    @click="dialogMode = 'attach'"
                                    class="flex-1"
                                >
                                    Vincular aluno existente
                                </Button>
                            </div>

                            <div v-if="dialogMode === 'create'" class="space-y-6">
                                <Form
                                    :action="`/school/parents/${props.parent.id}/students`"
                                    method="post"
                                    reset-on-success
                                    @success="createDialogOpen = false"
                                    v-slot="{ processing, errors }"
                                >
                                    <StudentForm
                                        :turmas="props.turmas"
                                        submit-label="Adicionar aluno"
                                        :processing="processing"
                                        :errors="errors"
                                    />
                                </Form>
                            </div>

                            <div v-else class="space-y-4">
                                <div class="relative">
                                    <label class="text-sm font-medium mb-2 block">
                                        Pesquisar aluno
                                    </label>
                                    <div class="relative">
                                        <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            ref="studentSearchInputRef"
                                            v-model="studentSearch"
                                            type="text"
                                            placeholder="Digite o nome do aluno..."
                                            class="pl-8"
                                            @focus="toggleStudentDropdown"
                                        />
                                    </div>

                                    <div
                                        v-if="isStudentDropdownOpen"
                                        class="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md"
                                    >
                                        <div class="max-h-[300px] overflow-y-auto p-1">
                                            <div v-if="isSearching" class="px-2 py-4 text-sm text-muted-foreground text-center">
                                                Buscando...
                                            </div>
                                            <button
                                                v-else-if="studentSearchResults.length > 0"
                                                v-for="student in studentSearchResults"
                                                :key="student.id"
                                                type="button"
                                                @click="selectStudent(student)"
                                                class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                                :class="{
                                                    'bg-accent text-accent-foreground': student.id === selectedStudentId,
                                                }"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span>{{ student.nome }}</span>
                                                    <Badge
                                                        v-if="student.nome_social"
                                                        variant="secondary"
                                                        class="text-xs"
                                                    >
                                                        {{ student.nome_social }}
                                                    </Badge>
                                                    <Badge
                                                        :variant="student.ativo ? 'default' : 'destructive'"
                                                        class="text-xs ml-auto"
                                                    >
                                                        {{ student.ativo ? 'Ativo' : 'Inativo' }}
                                                    </Badge>
                                                </div>
                                            </button>
                                            <div
                                                v-else-if="studentSearch.trim() && !isSearching"
                                                class="px-2 py-4 text-sm text-muted-foreground text-center"
                                            >
                                                Nenhum aluno encontrado
                                            </div>
                                            <div
                                                v-else-if="!studentSearch.trim()"
                                                class="px-2 py-4 text-sm text-muted-foreground text-center"
                                            >
                                                Digite para pesquisar...
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="selectedStudentId" class="rounded-lg border p-3 bg-muted/50">
                                    <p class="text-sm font-medium">Aluno selecionado:</p>
                                    <p class="text-sm text-muted-foreground">{{ selectedStudentName }}</p>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        @click="createDialogOpen = false"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        @click="attachExistingStudent"
                                        :disabled="!selectedStudentId"
                                    >
                                        Vincular aluno
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="props.parent.students && props.parent.students.length" class="mt-6 space-y-3">
                <div
                    v-for="student in props.parent.students"
                    :key="student.id"
                    class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <p class="font-medium">{{ student.nome }}</p>
                            <Badge :variant="student.ativo ? 'default' : 'destructive'">
                                {{ student.ativo ? 'Ativo' : 'Inativo' }}
                            </Badge>
                        </div>
                        <p v-if="student.nome_social" class="text-sm text-muted-foreground">
                            Nome social: {{ student.nome_social }}
                        </p>
                        <p v-if="student.turma" class="text-sm text-muted-foreground">
                            Turma: {{ student.turma.nome }}
                            <template v-if="student.turma.serie || student.turma.turma_letra">
                                ({{ [student.turma.serie, student.turma.turma_letra].filter(Boolean).join(' - ') }})
                            </template>
                            <template v-if="student.turma.ano_letivo">
                                - {{ student.turma.ano_letivo }}
                            </template>
                        </p>
                        <p v-if="student.data_nascimento" class="text-sm text-muted-foreground">
                            Data de nascimento: {{ new Date(student.data_nascimento).toLocaleDateString('pt-BR') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link :href="`/school/students/${student.id}`">
                                Ver aluno
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            @click="detachStudent(student.id)"
                        >
                            Remover vínculo
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-6 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Nenhum aluno vinculado. Clique em "Adicionar aluno" para cadastrar o primeiro.
            </div>
        </div>
    </AppLayout>
</template>

