<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface ExerciseData {
    id?: string;
    disciplina_id?: string;
    titulo?: string;
    descricao?: string | null;
    data_entrega?: string;
    anexo_url?: string | null;
    turma_id?: string;
    tipo_exercicio?: string;
}

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    ano_letivo?: number | string | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

const props = defineProps<{
    exerciseData?: ExerciseData;
    turmas?: Turma[];
    disciplinas?: Disciplina[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const disciplinaId = ref(props.exerciseData?.disciplina_id || '');
const titulo = ref(props.exerciseData?.titulo || '');
const descricao = ref(props.exerciseData?.descricao || '');
const dataEntrega = ref(props.exerciseData?.data_entrega || '');
const anexoFile = ref<File | null>(null);
const anexoUrl = ref(props.exerciseData?.anexo_url || '');
const turmaId = ref(props.exerciseData?.turma_id || '');
const tipoExercicio = ref(props.exerciseData?.tipo_exercicio || 'exercicio_caderno');

const isEdit = computed(() => !!props.exerciseData?.id);

watch(() => props.exerciseData, (newData) => {
    if (newData) {
        disciplinaId.value = newData.disciplina_id || '';
        titulo.value = newData.titulo || '';
        descricao.value = newData.descricao || '';
        dataEntrega.value = newData.data_entrega || '';
        anexoUrl.value = newData.anexo_url || '';
        turmaId.value = newData.turma_id || '';
        tipoExercicio.value = newData.tipo_exercicio || 'exercicio_caderno';
        anexoFile.value = null;
    }
}, { immediate: true, deep: true });

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        anexoFile.value = target.files[0];
        anexoUrl.value = '';
    }
};

const removeFile = () => {
    anexoFile.value = null;
    anexoUrl.value = '';
    const fileInput = document.getElementById('anexo') as HTMLInputElement;
    if (fileInput) {
        fileInput.value = '';
    }
};
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="disciplina_id">Disciplina</Label>
                <select
                    id="disciplina_id"
                    name="disciplina_id"
                    v-model="disciplinaId"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione uma disciplina</option>
                    <option
                        v-for="disciplina in disciplinas"
                        :key="disciplina.id"
                        :value="disciplina.id"
                    >
                        {{ disciplina.nome }}{{ disciplina.sigla ? ` (${disciplina.sigla})` : '' }}
                    </option>
                </select>
                <InputError :message="errors.disciplina_id" />
            </div>

            <div class="grid gap-2">
                <Label for="turma_id">Turma</Label>
                <select
                    id="turma_id"
                    name="turma_id"
                    v-model="turmaId"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione uma turma</option>
                    <option
                        v-for="turma in turmas"
                        :key="turma.id"
                        :value="turma.id"
                    >
                        {{ turma.nome }}
                        <template v-if="turma.serie || turma.ano_letivo">
                            ({{ [turma.serie, turma.ano_letivo].filter(Boolean).join(' - ') }})
                        </template>
                    </option>
                </select>
                <InputError :message="errors.turma_id" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="tipo_exercicio">Tipo de Exercício</Label>
            <select
                id="tipo_exercicio"
                name="tipo_exercicio"
                v-model="tipoExercicio"
                required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <option value="exercicio_caderno">Exercício de Caderno</option>
                <option value="exercicio_livro">Exercício de Livro</option>
                <option value="trabalho">Trabalho</option>
            </select>
            <InputError :message="errors.tipo_exercicio" />
        </div>

        <div class="grid gap-2">
            <Label for="titulo">Título</Label>
            <input
                id="titulo"
                name="titulo"
                v-model="titulo"
                type="text"
                placeholder="Ex: Exercícios sobre frações"
                required
                maxlength="255"
                class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.titulo" />
        </div>

        <div class="grid gap-2">
            <Label for="descricao">Descrição</Label>
            <textarea
                id="descricao"
                name="descricao"
                v-model="descricao"
                rows="4"
                placeholder="Descreva o exercício..."
                class="flex min-h-[80px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.descricao" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="data_entrega">Data de Entrega</Label>
                <input
                    id="data_entrega"
                    name="data_entrega"
                    v-model="dataEntrega"
                    type="date"
                    required
                    :min="isEdit ? undefined : new Date().toISOString().split('T')[0]"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.data_entrega" />
            </div>

            <div class="grid gap-2">
                <Label for="anexo">Anexo (opcional)</Label>
                <div class="space-y-2">
                    <input
                        id="anexo"
                        name="anexo"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.odt,.ods"
                        @change="handleFileChange"
                        class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90"
                    />
                    <p class="text-xs text-muted-foreground">
                        Formatos aceitos: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), Texto (.txt, .rtf), OpenDocument (.odt, .ods). Tamanho máximo: 10MB.
                    </p>
                    <div v-if="anexoFile" class="flex items-center gap-2 rounded-md bg-muted/50 p-2">
                        <span class="text-sm">{{ anexoFile.name }}</span>
                        <button
                            type="button"
                            @click="removeFile"
                            class="ml-auto text-sm text-destructive hover:underline"
                        >
                            Remover
                        </button>
                    </div>
                    <div v-else-if="anexoUrl" class="flex items-center gap-2 rounded-md bg-muted/50 p-2">
                        <span class="text-sm">Arquivo anexado anteriormente</span>
                        <a
                            :href="anexoUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-auto text-sm text-primary hover:underline"
                        >
                            Ver arquivo
                        </a>
                    </div>
                </div>
                <InputError :message="errors.anexo" />
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

