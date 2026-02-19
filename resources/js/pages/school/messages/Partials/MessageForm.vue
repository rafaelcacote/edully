<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save, ChevronDown, Search, X } from 'lucide-vue-next';
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
const alunoSearch = ref('');
const turmaSearch = ref('');
const isAlunoDropdownOpen = ref(false);
const isTurmaDropdownOpen = ref(false);
const alunoDropdownRef = ref<HTMLElement | null>(null);
const turmaDropdownRef = ref<HTMLElement | null>(null);
const alunoSearchInputRef = ref<HTMLInputElement | null>(null);
const turmaSearchInputRef = ref<HTMLInputElement | null>(null);

const isEdit = computed(() => !!props.messageData?.id);

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

// Limpar busca quando o aluno for selecionado
watch(alunoId, () => {
    if (!isAlunoDropdownOpen.value) {
        alunoSearch.value = '';
    }
});

// Limpar busca quando a turma for selecionada
watch(turmaId, () => {
    if (!isTurmaDropdownOpen.value) {
        turmaSearch.value = '';
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label>Destinatário</Label>
            <div class="flex gap-4 rounded-lg border border-input bg-background p-1">
                <button
                    type="button"
                    @click="switchRecipientType('aluno')"
                    class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        recipientType === 'aluno'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    Aluno
                </button>
                <button
                    type="button"
                    @click="switchRecipientType('turma')"
                    class="flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="
                        recipientType === 'turma'
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    Turma
                </button>
            </div>
        </div>

        <div v-if="recipientType === 'aluno'" class="grid gap-2">
            <Label for="aluno_id">Aluno</Label>
            <div class="relative" ref="alunoDropdownRef">
                <input
                    type="hidden"
                    id="aluno_id"
                    name="aluno_id"
                    :value="alunoId"
                />
                <button
                    type="button"
                    @click="toggleAlunoDropdown"
                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{
                        'text-muted-foreground': !selectedAlunoName,
                    }"
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
                    <div class="p-2 border-b">
                        <div class="relative">
                            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref="alunoSearchInputRef"
                                v-model="alunoSearch"
                                type="text"
                                placeholder="Digite para pesquisar..."
                                class="pl-8 h-9"
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
                            @click.stop="selectAluno(aluno)"
                            class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                            :class="{
                                'bg-accent text-accent-foreground': aluno.id === alunoId,
                            }"
                        >
                            {{ aluno.nome }}
                        </button>
                        <div
                            v-if="alunoSearch && filteredAlunos.length === 0"
                            class="px-2 py-1.5 text-sm text-muted-foreground text-center"
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
            <div class="relative" ref="turmaDropdownRef">
                <input
                    type="hidden"
                    id="turma_id"
                    name="turma_id"
                    :value="turmaId"
                />
                <button
                    type="button"
                    @click="toggleTurmaDropdown"
                    class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="{
                        'text-muted-foreground': !selectedTurmaName,
                    }"
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
                    <div class="p-2 border-b">
                        <div class="relative">
                            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref="turmaSearchInputRef"
                                v-model="turmaSearch"
                                type="text"
                                placeholder="Digite para pesquisar..."
                                class="pl-8 h-9"
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
                            @click.stop="selectTurma(turma)"
                            class="w-full rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground"
                            :class="{
                                'bg-accent text-accent-foreground': turma.id === turmaId,
                            }"
                        >
                            {{ turma.nome }}
                        </button>
                        <div
                            v-if="turmaSearch && filteredTurmas.length === 0"
                            class="px-2 py-1.5 text-sm text-muted-foreground text-center"
                        >
                            Nenhuma turma encontrada
                        </div>
                    </div>
                </div>
            </div>
            <InputError :message="errors.turma_id" />
        </div>

        <div class="grid gap-2">
            <Label for="titulo">Título</Label>
            <input
                id="titulo"
                name="titulo"
                v-model="titulo"
                type="text"
                placeholder="Ex: Informações sobre a prova"
                required
                maxlength="255"
                class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.titulo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="tipo">Tipo</Label>
                <select
                    id="tipo"
                    name="tipo"
                    v-model="tipo"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="outro">Outro</option>
                    <option value="informativo">Informativo</option>
                    <option value="atencao">Atenção</option>
                    <option value="aviso">Aviso</option>
                    <option value="lembrete">Lembrete</option>
                </select>
                <InputError :message="errors.tipo" />
            </div>

            <div class="grid gap-2">
                <Label for="prioridade">Prioridade</Label>
                <select
                    id="prioridade"
                    name="prioridade"
                    v-model="prioridade"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="normal">Normal</option>
                    <option value="alta">Alta</option>
                    <option value="media">Média</option>
                </select>
                <InputError :message="errors.prioridade" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="conteudo">Conteúdo</Label>
            <textarea
                id="conteudo"
                name="conteudo"
                v-model="conteudo"
                rows="6"
                placeholder="Digite o conteúdo da mensagem..."
                required
                class="flex min-h-[120px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.conteudo" />
        </div>

        <div class="grid gap-2">
            <Label for="anexo_url">URL do Anexo (opcional)</Label>
            <input
                id="anexo_url"
                name="anexo_url"
                v-model="anexoUrl"
                type="url"
                placeholder="https://..."
                maxlength="2048"
                class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.anexo_url" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
