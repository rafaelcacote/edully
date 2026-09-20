<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    FileText,
    FolderOpen,
    Image as ImageIcon,
    Save,
    Search,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Aluno {
    id: string;
    nome: string;
    turma: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    alunos: Aluno[];
    categorias: Option[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documentos',
        href: '/school/documentos',
    },
    {
        title: 'Enviar documento',
        href: '#',
    },
];

const alunoId = ref('');
const alunoSearch = ref('');
const isAlunoDropdownOpen = ref(false);
const alunoDropdownRef = ref<HTMLElement | null>(null);
const alunoSearchInputRef = ref<HTMLInputElement | null>(null);
const anexoFile = ref<File | null>(null);
const anexoPreview = ref<string | null>(null);
const anexoInputRef = ref<HTMLInputElement | null>(null);

const filteredAlunos = computed(() => {
    const term = alunoSearch.value.trim().toLowerCase();
    if (!term) {
        return props.alunos;
    }

    return props.alunos.filter((aluno) => {
        const nome = aluno.nome.toLowerCase();
        const turma = (aluno.turma ?? '').toLowerCase();

        return nome.includes(term) || turma.includes(term);
    });
});

const selectedAlunoLabel = computed(() => {
    const aluno = props.alunos.find((item) => item.id === alunoId.value);
    if (!aluno) {
        return '';
    }

    return aluno.turma ? `${aluno.nome} — ${aluno.turma}` : aluno.nome;
});

const isImageAnexo = computed(() => {
    const source = anexoFile.value?.type || '';
    return source.startsWith('image/');
});

function selectAluno(id: string) {
    alunoId.value = id;
    isAlunoDropdownOpen.value = false;
    alunoSearch.value = '';
}

function handleAnexoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        anexoFile.value = target.files[0];
        anexoPreview.value = target.files[0].name;
    }
}

function removeAnexo() {
    anexoFile.value = null;
    anexoPreview.value = null;
    if (anexoInputRef.value) {
        anexoInputRef.value.value = '';
    }
}

function handleClickOutside(event: MouseEvent) {
    if (
        alunoDropdownRef.value &&
        !alunoDropdownRef.value.contains(event.target as Node)
    ) {
        isAlunoDropdownOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Enviar documento" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Enviar documento"
                        description="Envie um documento ou declaração ao responsável do aluno"
                        :icon="FolderOpen"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/documentos" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    action="/school/documentos"
                    method="post"
                    enctype="multipart/form-data"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="aluno_id" :value="alunoId" />

                    <div class="relative space-y-2" ref="alunoDropdownRef">
                        <Label>Aluno *</Label>
                        <button
                            type="button"
                            class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 text-sm"
                            @click="
                                isAlunoDropdownOpen = !isAlunoDropdownOpen;
                                if (isAlunoDropdownOpen) {
                                    setTimeout(() => alunoSearchInputRef?.focus(), 0);
                                }
                            "
                        >
                            <span :class="selectedAlunoLabel ? '' : 'text-muted-foreground'">
                                {{ selectedAlunoLabel || 'Selecione o aluno' }}
                            </span>
                            <ChevronDown class="h-4 w-4 text-muted-foreground" />
                        </button>

                        <div
                            v-if="isAlunoDropdownOpen"
                            class="absolute z-20 mt-1 w-full max-w-xl rounded-md border bg-popover p-2 shadow-md"
                        >
                            <div class="relative mb-2">
                                <Search
                                    class="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground"
                                />
                                <Input
                                    ref="alunoSearchInputRef"
                                    v-model="alunoSearch"
                                    class="pl-8"
                                    placeholder="Buscar por aluno ou turma..."
                                />
                            </div>
                            <div class="max-h-56 overflow-y-auto">
                                <button
                                    v-for="aluno in filteredAlunos"
                                    :key="aluno.id"
                                    type="button"
                                    class="flex w-full flex-col rounded-md px-3 py-2 text-left text-sm hover:bg-accent"
                                    @click="selectAluno(aluno.id)"
                                >
                                    <span class="font-medium">{{ aluno.nome }}</span>
                                    <span
                                        v-if="aluno.turma"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ aluno.turma }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Sem turma ativa
                                    </span>
                                </button>
                                <p
                                    v-if="filteredAlunos.length === 0"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    Nenhum aluno encontrado.
                                </p>
                            </div>
                        </div>
                        <InputError :message="errors.aluno_id" />
                    </div>

                    <div class="space-y-2">
                        <Label for="titulo">Título *</Label>
                        <Input id="titulo" name="titulo" required maxlength="255" />
                        <InputError :message="errors.titulo" />
                    </div>

                    <div class="space-y-2">
                        <Label for="categoria_declaracao">Categoria (opcional)</Label>
                        <select
                            id="categoria_declaracao"
                            name="categoria_declaracao"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Sem categoria</option>
                            <option
                                v-for="categoria in props.categorias"
                                :key="categoria.value"
                                :value="categoria.value"
                            >
                                {{ categoria.label }}
                            </option>
                        </select>
                        <InputError :message="errors.categoria_declaracao" />
                    </div>

                    <div class="space-y-2">
                        <Label for="descricao">Descrição</Label>
                        <textarea
                            id="descricao"
                            name="descricao"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="errors.descricao" />
                    </div>

                    <div class="space-y-2">
                        <Label>Arquivo *</Label>
                        <input
                            ref="anexoInputRef"
                            type="file"
                            name="anexo"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            class="hidden"
                            @change="handleAnexoChange"
                        />
                        <div class="flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="anexoInputRef?.click()"
                            >
                                <Upload class="mr-2 h-4 w-4" />
                                Selecionar arquivo
                            </Button>
                            <div
                                v-if="anexoPreview"
                                class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                            >
                                <ImageIcon v-if="isImageAnexo" class="h-4 w-4" />
                                <FileText v-else class="h-4 w-4" />
                                <span>{{ anexoPreview }}</span>
                                <button type="button" @click="removeAnexo">
                                    <X class="h-4 w-4 text-muted-foreground" />
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">PDF, JPG ou PNG — máx. 10 MB</p>
                        <InputError :message="errors.anexo" />
                    </div>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing || !alunoId">
                            <Save class="mr-2 h-4 w-4" />
                            {{ processing ? 'Enviando...' : 'Enviar documento' }}
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
