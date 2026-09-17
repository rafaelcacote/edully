<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save, ChevronDown, Search, X, Upload, FileText, Image as ImageIcon } from 'lucide-vue-next';
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';

interface MessageData {
    id?: string;
    aluno_id?: string;
    titulo?: string;
    conteudo?: string | null;
    tipo?: string | null;
    prioridade?: string | null;
    anexo_url?: string | null;
}

interface Aluno {
    id: string;
    nome: string;
}

interface Turma {
    id: string;
    nome: string;
}

const props = defineProps<{
    messageData?: MessageData;
    alunos?: Aluno[];
    turmas?: Turma[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const recipientType = ref<'aluno' | 'turma'>('aluno');
const alunoId = ref(props.messageData?.aluno_id || '');
const turmaId = ref('');
const titulo = ref(props.messageData?.titulo || '');
const conteudo = ref(props.messageData?.conteudo || '');
const tipo = ref(props.messageData?.tipo || 'outro');
const prioridade = ref(props.messageData?.prioridade || 'normal');
const anexoUrl = ref(props.messageData?.anexo_url || '');
const anexoFile = ref<File | null>(null);
const anexoPreview = ref<string | null>(props.messageData?.anexo_url ?? null);
const anexoRemoved = ref(false);
const alunoSearch = ref('');
const turmaSearch = ref('');
const isAlunoDropdownOpen = ref(false);
const isTurmaDropdownOpen = ref(false);
const alunoDropdownRef = ref<HTMLElement | null>(null);
const turmaDropdownRef = ref<HTMLElement | null>(null);
const alunoSearchInputRef = ref<HTMLInputElement | null>(null);
const turmaSearchInputRef = ref<HTMLInputElement | null>(null);

const isEdit = computed(() => !!props.messageData?.id);
const isImageAnexo = computed(() => {
    const source = anexoFile.value?.type || anexoPreview.value || '';
    if (typeof source === 'string' && source.startsWith('image/')) {
        return true;
    }
    if (typeof source === 'string') {
        return /\.(jpg|jpeg|png|webp)(\?|$)/i.test(source);
    }
    return false;
});
const anexoLabel = computed(() => {
    if (anexoFile.value) {
        return anexoFile.value.name;
    }
    if (anexoPreview.value) {
        return isImageAnexo.value ? 'Imagem anexada' : 'Arquivo anexado';
    }
    return '';
});

function handleAnexoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        anexoFile.value = target.files[0];
        anexoPreview.value = target.files[0].name;
        anexoUrl.value = '';
        anexoRemoved.value = false;
    }
}

function removeAnexo() {
    anexoFile.value = null;
    anexoPreview.value = null;
    anexoUrl.value = '';
    anexoRemoved.value = true;
    const input = document.querySelector('input[name="anexo"]') as HTMLInputElement | null;
    if (input) {
        input.value = '';
    }
}

const selectedAlunoName = computed(() => {
    if (!alunoId.value || !props.alunos) {
        return '';
    }
    const aluno = props.alunos.find((a) => a.id === alunoId.value);
    return aluno?.nome || '';
});

const selectedTurmaName = computed(() => {
    if (!turmaId.value || !props.turmas) {
        return '';
    }
    const turma = props.turmas.find((t) => t.id === turmaId.value);
    return turma?.nome || '';
});

const filteredAlunos = computed(() => {
    if (!props.alunos) {
        return [];
    }

    if (!alunoSearch.value.trim()) {
        return props.alunos;
    }

    const searchTerm = alunoSearch.value.toLowerCase().trim();
    return props.alunos.filter((aluno) =>
        aluno.nome.toLowerCase().includes(searchTerm)
    );
});

const filteredTurmas = computed(() => {
    if (!props.turmas) {
        return [];
    }

    if (!turmaSearch.value.trim()) {
        return props.turmas;
    }

    const searchTerm = turmaSearch.value.toLowerCase().trim();
    return props.turmas.filter((turma) =>
        turma.nome.toLowerCase().includes(searchTerm)
    );
});

function toggleAlunoDropdown() {
    isAlunoDropdownOpen.value = !isAlunoDropdownOpen.value;
    if (isAlunoDropdownOpen.value) {
        setTimeout(() => {
            alunoSearchInputRef.value?.focus();
        }, 100);
    } else {
        if (!alunoId.value) {
            alunoSearch.value = '';
        }
    }
}

function toggleTurmaDropdown() {
    isTurmaDropdownOpen.value = !isTurmaDropdownOpen.value;
    if (isTurmaDropdownOpen.value) {
        setTimeout(() => {
            turmaSearchInputRef.value?.focus();
        }, 100);
    } else {
        if (!turmaId.value) {
            turmaSearch.value = '';
        }
    }
}

function selectAluno(aluno: Aluno) {
    alunoId.value = aluno.id;
    alunoSearch.value = '';
    isAlunoDropdownOpen.value = false;
}

function selectTurma(turma: Turma) {
    turmaId.value = turma.id;
    turmaSearch.value = '';
    isTurmaDropdownOpen.value = false;
}

function handleClickOutside(event: MouseEvent) {
    if (alunoDropdownRef.value && !alunoDropdownRef.value.contains(event.target as Node)) {
        isAlunoDropdownOpen.value = false;
        if (!alunoId.value) {
            alunoSearch.value = '';
        }
    }
    if (turmaDropdownRef.value && !turmaDropdownRef.value.contains(event.target as Node)) {
        isTurmaDropdownOpen.value = false;
        if (!turmaId.value) {
            turmaSearch.value = '';
        }
    }
}

function switchRecipientType(type: 'aluno' | 'turma') {
    recipientType.value = type;
    if (type === 'aluno') {
        turmaId.value = '';
        turmaSearch.value = '';
    } else {
        alunoId.value = '';
        alunoSearch.value = '';
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

watch(() => props.messageData, (newData) => {
    if (newData) {
        alunoId.value = newData.aluno_id || '';
        titulo.value = newData.titulo || '';
        conteudo.value = newData.conteudo || '';
        tipo.value = newData.tipo || 'outro';
        prioridade.value = newData.prioridade || 'normal';
        anexoUrl.value = newData.anexo_url || '';
    }
}, { immediate: true, deep: true });

watch(alunoId, () => {
    if (!isAlunoDropdownOpen.value) {
        alunoSearch.value = '';
    }
});

watch(turmaId, () => {
    if (!isTurmaDropdownOpen.value) {
        turmaSearch.value = '';
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-4">
            <div class="grid gap-2">
                <Label>Destinatário</Label>
                <div class="flex gap-4 rounded-lg border border-input bg-background p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            recipientType === 'aluno'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        "
                        @click="switchRecipientType('aluno')"
                    >
                        Aluno
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            recipientType === 'turma'
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                        "
                        @click="switchRecipientType('turma')"
                    >
                        Turma
                    </button>
                </div>
            </div>

            <div v-if="recipientType === 'aluno'" class="grid gap-2">
                <Label for="aluno_id">Aluno</Label>
                <div ref="alunoDropdownRef" class="relative">
                    <input
                        id="aluno_id"
                        type="hidden"
                        name="aluno_id"
                        :value="alunoId"
                    />
                    <button
                        type="button"
                        class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{
                            'text-muted-foreground': !selectedAlunoName,
                        }"
                        @click="toggleAlunoDropdown"
                    >
                        <span>{{ selectedAlunoName || 'Selecione um aluno' }}</span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': isAlunoDropdownOpen }"
                        />
                    </button>

                    <div
                        v-if="isAlunoDropdownOpen"
                        class="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md"
                    >
                        <div class="border-b p-2">
                            <div class="relative">
                                <Search class="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref="alunoSearchInputRef"
                                    v-model="alunoSearch"
                                    type="text"
                                    placeholder="Digite para pesquisar..."
                                    class="h-9 pl-8"
                                    @input.stop
                                    @click.stop
                                />
                            </div>
                        </div>
                        <div class="max-h-[200px] overflow-y-auto p-1">
                            <button
                                v-for="aluno in filteredAlunos"
                                :key="aluno.id"
                                type="button"
                                class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground': aluno.id === alunoId,
                                }"
                                @click.stop="selectAluno(aluno)"
                            >
                                {{ aluno.nome }}
                            </button>
                            <div
                                v-if="alunoSearch && filteredAlunos.length === 0"
                                class="px-2 py-1.5 text-center text-sm text-muted-foreground"
                            >
                                Nenhum aluno encontrado
                            </div>
                        </div>
                    </div>
                </div>
                <InputError :message="errors.aluno_id" />
            </div>

            <div v-else class="grid gap-2">
                <Label for="turma_id">Turma</Label>
                <div ref="turmaDropdownRef" class="relative">
                    <input
                        id="turma_id"
                        type="hidden"
                        name="turma_id"
                        :value="turmaId"
                    />
                    <button
                        type="button"
                        class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :class="{
                            'text-muted-foreground': !selectedTurmaName,
                        }"
                        @click="toggleTurmaDropdown"
                    >
                        <span>{{ selectedTurmaName || 'Selecione uma turma' }}</span>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="{ 'rotate-180': isTurmaDropdownOpen }"
                        />
                    </button>

                    <div
                        v-if="isTurmaDropdownOpen"
                        class="absolute z-50 mt-1 w-full rounded-md border bg-popover shadow-md"
                    >
                        <div class="border-b p-2">
                            <div class="relative">
                                <Search class="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    ref="turmaSearchInputRef"
                                    v-model="turmaSearch"
                                    type="text"
                                    placeholder="Digite para pesquisar..."
                                    class="h-9 pl-8"
                                    @input.stop
                                    @click.stop
                                />
                            </div>
                        </div>
                        <div class="max-h-[200px] overflow-y-auto p-1">
                            <button
                                v-for="turma in filteredTurmas"
                                :key="turma.id"
                                type="button"
                                class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                                :class="{
                                    'bg-accent text-accent-foreground': turma.id === turmaId,
                                }"
                                @click.stop="selectTurma(turma)"
                            >
                                {{ turma.nome }}
                            </button>
                            <div
                                v-if="turmaSearch && filteredTurmas.length === 0"
                                class="px-2 py-1.5 text-center text-sm text-muted-foreground"
                            >
                                Nenhuma turma encontrada
                            </div>
                        </div>
                    </div>
                </div>
                <InputError :message="errors.turma_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="titulo">Título</Label>
            <input
                id="titulo"
                v-model="titulo"
                name="titulo"
                type="text"
                placeholder="Ex: Informações sobre a prova"
                required
                maxlength="255"
                class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.titulo" />
        </div>

        <div class="grid gap-2">
            <Label for="conteudo">Conteúdo</Label>
            <textarea
                id="conteudo"
                v-model="conteudo"
                name="conteudo"
                rows="6"
                placeholder="Digite o conteúdo do recado..."
                required
                class="flex min-h-[120px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.conteudo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            <div class="grid gap-2">
                <Label for="tipo">Tipo</Label>
                <select
                    id="tipo"
                    v-model="tipo"
                    name="tipo"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="outro">Outro</option>
                    <option value="informativo">Informativo</option>
                    <option value="atencao">Atenção</option>
                    <option value="aviso">Urgente</option>
                    <option value="lembrete">Lembrete</option>
                </select>
                <InputError :message="errors.tipo" />
            </div>

            <div class="grid gap-2">
                <Label for="prioridade">Prioridade</Label>
                <select
                    id="prioridade"
                    v-model="prioridade"
                    name="prioridade"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="media">Média</option>
                </select>
                <InputError :message="errors.prioridade" />
            </div>

            <div class="grid gap-2 sm:col-span-2 xl:col-span-1">
                <Label for="anexo">Anexo (opcional)</Label>

                <div v-if="!anexoPreview && !anexoFile" class="space-y-2">
                    <label
                        for="anexo"
                        class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm hover:bg-accent"
                    >
                        <Upload class="h-4 w-4" />
                        <span>Selecionar PDF ou imagem</span>
                    </label>
                    <input
                        id="anexo"
                        name="anexo"
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="handleAnexoChange"
                    />
                    <p class="text-xs text-muted-foreground">
                        PDF, JPG, PNG ou WEBP. Máx. 10MB.
                    </p>
                    <input
                        v-if="anexoRemoved && !anexoFile"
                        type="hidden"
                        name="anexo_url"
                        value=""
                    />
                </div>

                <div v-else class="space-y-2">
                    <div class="flex items-center gap-2 rounded-lg border border-input bg-muted/50 p-3">
                        <ImageIcon v-if="isImageAnexo" class="h-5 w-5 text-muted-foreground" />
                        <FileText v-else class="h-5 w-5 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ anexoLabel }}
                            </p>
                            <p
                                v-if="props.messageData?.anexo_url && !anexoFile"
                                class="text-xs text-muted-foreground"
                            >
                                <a
                                    :href="props.messageData.anexo_url"
                                    target="_blank"
                                    class="text-blue-500 hover:underline"
                                >
                                    Ver anexo atual
                                </a>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded p-1 hover:bg-destructive/10 hover:text-destructive"
                            @click="removeAnexo"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <label
                        for="anexo"
                        class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm hover:bg-accent"
                    >
                        <Upload class="h-4 w-4" />
                        <span>Alterar arquivo</span>
                    </label>
                    <input
                        id="anexo"
                        name="anexo"
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp,.pdf,.jpg,.jpeg,.png,.webp"
                        class="hidden"
                        @change="handleAnexoChange"
                    />
                </div>

                <InputError :message="errors.anexo" />
                <InputError :message="errors.anexo_url" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
