<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TestData {
    id?: string;
    disciplina_id?: string;
    titulo?: string;
    descricao?: string | null;
    data_prova?: string;
    horario?: string | null;
    sala?: string | null;
    duracao_minutos?: number | null;
    turma_id?: string;
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

const props = withDefaults(defineProps<{
    testData?: TestData;
    turmas?: Turma[];
    disciplinas?: Disciplina[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>(), {
    turmas: () => [],
    disciplinas: () => [],
    errors: () => ({}),
});

const disciplinaId = ref(props.testData?.disciplina_id || '');
const titulo = ref(props.testData?.titulo || '');
const descricao = ref(props.testData?.descricao || '');
const dataProva = ref(props.testData?.data_prova || '');
const horario = ref(props.testData?.horario || '');
const sala = ref(props.testData?.sala || '');
const duracaoMinutos = ref(props.testData?.duracao_minutos || '');
const turmaId = ref(props.testData?.turma_id || '');

// Garantir que disciplinas seja sempre um array
const disciplinasList = computed(() => props.disciplinas || []);
const turmasList = computed(() => props.turmas || []);

const isEdit = computed(() => !!props.testData?.id);

watch(() => props.testData, (newData) => {
    if (newData) {
        disciplinaId.value = newData.disciplina_id || '';
        titulo.value = newData.titulo || '';
        descricao.value = newData.descricao || '';
        dataProva.value = newData.data_prova || '';
        horario.value = newData.horario || '';
        sala.value = newData.sala || '';
        duracaoMinutos.value = newData.duracao_minutos?.toString() || '';
        turmaId.value = newData.turma_id || '';
    }
}, { immediate: true, deep: true });
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
                        v-for="disciplina in (disciplinasList || [])"
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
                        v-for="turma in (turmasList || [])"
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
            <Label for="titulo">Título</Label>
            <input
                id="titulo"
                name="titulo"
                v-model="titulo"
                type="text"
                placeholder="Ex: Prova de Matemática - Unidade 1"
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
                placeholder="Descreva a prova..."
                class="flex min-h-[80px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.descricao" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="data_prova">Data da Prova</Label>
                <input
                    id="data_prova"
                    name="data_prova"
                    v-model="dataProva"
                    type="date"
                    required
                    :min="isEdit ? undefined : new Date().toISOString().split('T')[0]"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.data_prova" />
            </div>

            <div class="grid gap-2">
                <Label for="horario">Horário (opcional)</Label>
                <input
                    id="horario"
                    name="horario"
                    v-model="horario"
                    type="time"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.horario" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="sala">Sala (opcional)</Label>
                <input
                    id="sala"
                    name="sala"
                    v-model="sala"
                    type="text"
                    placeholder="Ex: Sala 201"
                    maxlength="50"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.sala" />
            </div>

            <div class="grid gap-2">
                <Label for="duracao_minutos">Duração (minutos) (opcional)</Label>
                <input
                    id="duracao_minutos"
                    name="duracao_minutos"
                    v-model="duracaoMinutos"
                    type="number"
                    placeholder="Ex: 90"
                    min="1"
                    max="1440"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.duracao_minutos" />
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

