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
import { ArrowLeft, Check, Edit, Search, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
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
    data_nascimento?: string | null;
    observacoes?: string | null;
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
const studentSearchResults = ref<SearchStudent[]>([]);
const isSearching = ref(false);
const selectedStudent = ref<SearchStudent | null>(null);
const highlightedIndex = ref(-1);
const hiddenLinkedCount = ref(0);
const isAttaching = ref(false);
const studentSearchFieldRef = ref<HTMLElement | null>(null);

interface SearchTurma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: string | number | null;
}

interface SearchStudent {
    id: string;
    nome: string;
    nome_social?: string | null;
    foto_url?: string | null;
    ativo: boolean;
    turma?: SearchTurma | null;
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
let searchRequestId = 0;

function resetAttachState(): void {
    selectedStudent.value = null;
    studentSearch.value = '';
    studentSearchResults.value = [];
    highlightedIndex.value = -1;
    hiddenLinkedCount.value = 0;
    isSearching.value = false;
    isAttaching.value = false;
    searchRequestId += 1;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
}

function studentInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() ?? '').join('');
}

function turmaLabel(turma?: SearchTurma | null): string {
    if (!turma) {
        return '';
    }

    const details = [turma.serie, turma.turma_letra].filter(Boolean).join(' ');
    const year = turma.ano_letivo ? String(turma.ano_letivo) : '';

    return [turma.nome, details, year].filter(Boolean).join(' · ');
}

async function searchStudents(): Promise<void> {
    const query = studentSearch.value.trim();

    if (query.length < 2) {
        studentSearchResults.value = [];
        hiddenLinkedCount.value = 0;
        isSearching.value = false;

        return;
    }

    const requestId = ++searchRequestId;
    isSearching.value = true;

    try {
        const response = await fetch(`/school/students/search?search=${encodeURIComponent(query)}&limit=20`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (requestId !== searchRequestId) {
            return;
        }

        if (!response.ok) {
            throw new Error(`Erro ao buscar alunos: ${response.status}`);
        }

        const data = await response.json();
        const linkedStudentIds = new Set(props.parent.students?.map((student) => student.id) ?? []);
        const students = (data.students || []) as SearchStudent[];

        hiddenLinkedCount.value = students.filter((student) => linkedStudentIds.has(student.id)).length;
        studentSearchResults.value = students.filter((student) => !linkedStudentIds.has(student.id));
        highlightedIndex.value = studentSearchResults.value.length > 0 ? 0 : -1;
    } catch (error) {
        if (requestId !== searchRequestId) {
            return;
        }

        console.error('Erro ao buscar alunos:', error);
        studentSearchResults.value = [];
        hiddenLinkedCount.value = 0;
        highlightedIndex.value = -1;
    } finally {
        if (requestId === searchRequestId) {
            isSearching.value = false;
        }
    }
}

watch(studentSearch, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    highlightedIndex.value = -1;
    const query = studentSearch.value.trim();

    if (query.length < 2) {
        studentSearchResults.value = [];
        hiddenLinkedCount.value = 0;
        isSearching.value = false;
        searchRequestId += 1;

        return;
    }

    isSearching.value = true;
    searchTimeout = setTimeout(() => {
        searchStudents();
    }, 250);
});

function selectStudent(student: SearchStudent): void {
    selectedStudent.value = student;
    highlightedIndex.value = studentSearchResults.value.findIndex((item) => item.id === student.id);
}

function clearSelectedStudent(): void {
    selectedStudent.value = null;
}

function moveHighlight(direction: 1 | -1): void {
    if (studentSearchResults.value.length === 0) {
        return;
    }

    const lastIndex = studentSearchResults.value.length - 1;
    const nextIndex = highlightedIndex.value < 0
        ? (direction === 1 ? 0 : lastIndex)
        : Math.min(lastIndex, Math.max(0, highlightedIndex.value + direction));

    highlightedIndex.value = nextIndex;
}

function confirmHighlightedStudent(): void {
    const student = studentSearchResults.value[highlightedIndex.value];

    if (student) {
        selectStudent(student);
    }
}

function onStudentSearchKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveHighlight(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveHighlight(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        confirmHighlightedStudent();
    }
}

function attachExistingStudent(): void {
    if (!selectedStudent.value || isAttaching.value) {
        return;
    }

    isAttaching.value = true;

    router.post(`/school/parents/${props.parent.id}/students/attach`, {
        student_id: selectedStudent.value.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            createDialogOpen.value = false;
            dialogMode.value = 'create';
            resetAttachState();
        },
        onFinish: () => {
            isAttaching.value = false;
        },
    });
}

function focusStudentSearch(): void {
    nextTick(() => {
        studentSearchFieldRef.value?.querySelector('input')?.focus();
    });
}

watch(createDialogOpen, (isOpen) => {
    if (!isOpen) {
        dialogMode.value = 'create';
        resetAttachState();

        return;
    }

    if (dialogMode.value === 'attach') {
        focusStudentSearch();
    }
});

watch(dialogMode, (mode) => {
    if (mode === 'attach' && createDialogOpen.value) {
        focusStudentSearch();
    }
});

const searchHint = computed(() => {
    const query = studentSearch.value.trim();

    if (query.length === 1) {
        return 'Digite mais uma letra para buscar.';
    }

    return 'Busque pelo nome ou nome social. Alunos já vinculados a este responsável ficam de fora.';
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
                    <Button as-child class="rounded-lg">
                        <Link :href="`/school/parents/${props.parent.id}/edit`" class="flex items-center gap-2">
                            <Edit class="h-4 w-4" />
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
                                <p class="text-sm font-medium text-muted-foreground">Data de nascimento</p>
                                <p class="mt-1">
                                    {{ props.parent.data_nascimento ? new Date(props.parent.data_nascimento).toLocaleDateString('pt-BR') : '—' }}
                                </p>
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
                            <div class="sm:col-span-2">
                                <p class="text-sm font-medium text-muted-foreground">Observações</p>
                                <p class="mt-1 whitespace-pre-wrap">{{ props.parent.observacoes || '—' }}</p>
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

                <div v-if="!props.parent.ativo" class="text-sm text-muted-foreground">
                    Responsável inativo — não é possível vincular novos alunos.
                </div>
                <Dialog v-else v-model:open="createDialogOpen">
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
                                {{ dialogMode === 'create' ? 'Crie o aluno e o vínculo será feito automaticamente.' : 'Busque pelo nome, escolha o aluno na lista e confirme o vínculo. A seleção permanece mesmo se você continuar pesquisando.' }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="mt-2">
                            <div class="mb-5 grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                    :class="dialogMode === 'create' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="dialogMode = 'create'"
                                >
                                    <UserPlus class="h-4 w-4" />
                                    Criar novo aluno
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                                    :class="dialogMode === 'attach' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                                    @click="dialogMode = 'attach'"
                                >
                                    <Users class="h-4 w-4" />
                                    Vincular existente
                                </button>
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
                                <div ref="studentSearchFieldRef" class="space-y-2">
                                    <label for="existing-student-search" class="text-sm font-medium">
                                        Buscar aluno
                                    </label>
                                    <div class="relative">
                                        <Search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="existing-student-search"
                                            v-model="studentSearch"
                                            type="text"
                                            role="combobox"
                                            autocomplete="off"
                                            placeholder="Nome ou nome social"
                                            class="pr-10 pl-9"
                                            :aria-expanded="studentSearch.trim().length >= 2"
                                            aria-controls="existing-student-results"
                                            aria-autocomplete="list"
                                            @keydown="onStudentSearchKeydown"
                                        />
                                        <button
                                            v-if="studentSearch"
                                            type="button"
                                            class="absolute top-1/2 right-2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                                            aria-label="Limpar busca"
                                            @click="studentSearch = ''"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{ searchHint }}
                                    </p>
                                </div>

                                <div
                                    v-if="selectedStudent"
                                    class="flex items-center gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3"
                                >
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-background text-xs font-semibold">
                                        <img
                                            v-if="selectedStudent.foto_url"
                                            :src="selectedStudent.foto_url"
                                            :alt="`Foto de ${selectedStudent.nome}`"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{ studentInitials(selectedStudent.nome) }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium">{{ selectedStudent.nome }}</p>
                                        <p class="truncate text-xs text-muted-foreground">
                                            {{ turmaLabel(selectedStudent.turma) || 'Sem turma ativa' }}
                                            <template v-if="selectedStudent.nome_social">
                                                · {{ selectedStudent.nome_social }}
                                            </template>
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="clearSelectedStudent"
                                    >
                                        Trocar
                                    </Button>
                                </div>

                                <div
                                    id="existing-student-results"
                                    role="listbox"
                                    aria-label="Alunos encontrados"
                                    class="max-h-72 overflow-y-auto rounded-lg border"
                                >
                                    <div
                                        v-if="studentSearch.trim().length < 2"
                                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        A lista aparece aqui conforme você digita.
                                    </div>
                                    <div
                                        v-else-if="isSearching && studentSearchResults.length === 0"
                                        class="space-y-2 p-3"
                                    >
                                        <div
                                            v-for="placeholder in 3"
                                            :key="placeholder"
                                            class="h-14 animate-pulse rounded-md bg-muted"
                                        />
                                    </div>
                                    <div
                                        v-else-if="studentSearchResults.length === 0"
                                        class="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        <p>Nenhum aluno disponível para este nome.</p>
                                        <p v-if="hiddenLinkedCount > 0" class="mt-1">
                                            {{ hiddenLinkedCount === 1 ? 'O aluno encontrado já está vinculado a este responsável.' : `${hiddenLinkedCount} alunos encontrados já estão vinculados a este responsável.` }}
                                        </p>
                                    </div>
                                    <div v-else class="p-1">
                                        <button
                                            v-for="(student, index) in studentSearchResults"
                                            :key="student.id"
                                            type="button"
                                            role="option"
                                            :aria-selected="selectedStudent?.id === student.id"
                                            class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition-colors hover:bg-accent"
                                            :class="{
                                                'bg-accent': index === highlightedIndex || selectedStudent?.id === student.id,
                                            }"
                                            @mouseenter="highlightedIndex = index"
                                            @click="selectStudent(student)"
                                        >
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border bg-muted text-xs font-semibold">
                                                <img
                                                    v-if="student.foto_url"
                                                    :src="student.foto_url"
                                                    :alt="`Foto de ${student.nome}`"
                                                    class="h-full w-full object-cover"
                                                />
                                                <span v-else>{{ studentInitials(student.nome) }}</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="truncate text-sm font-medium">{{ student.nome }}</span>
                                                    <Badge
                                                        :variant="student.ativo ? 'secondary' : 'destructive'"
                                                        class="shrink-0"
                                                    >
                                                        {{ student.ativo ? 'Ativo' : 'Inativo' }}
                                                    </Badge>
                                                </div>
                                                <p class="truncate text-xs text-muted-foreground">
                                                    {{ turmaLabel(student.turma) || 'Sem turma ativa' }}
                                                    <template v-if="student.nome_social">
                                                        · Nome social: {{ student.nome_social }}
                                                    </template>
                                                </p>
                                            </div>
                                            <Check
                                                v-if="selectedStudent?.id === student.id"
                                                class="h-4 w-4 shrink-0 text-primary"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="createDialogOpen = false"
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        type="button"
                                        :disabled="!selectedStudent || isAttaching"
                                        @click="attachExistingStudent"
                                    >
                                        {{ isAttaching ? 'Vinculando...' : 'Vincular aluno' }}
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
                <template v-if="props.parent.ativo">
                    Nenhum aluno vinculado. Clique em "Adicionar aluno" para cadastrar o primeiro.
                </template>
                <template v-else>
                    Nenhum aluno vinculado. Reative o responsável para vincular novos alunos.
                </template>
            </div>
        </div>
    </AppLayout>
</template>

