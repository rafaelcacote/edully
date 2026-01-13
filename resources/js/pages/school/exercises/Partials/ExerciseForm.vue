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
const anexoUrl = ref(props.exerciseData?.anexo_url || '');
const turmaId = ref(props.exerciseData?.turma_id || '');

const isEdit = computed(() => !!props.exerciseData?.id);

watch(() => props.exerciseData, (newData) => {
    if (newData) {
        disciplinaId.value = newData.disciplina_id || '';
        titulo.value = newData.titulo || '';
        descricao.value = newData.descricao || '';
        dataEntrega.value = newData.data_entrega || '';
        anexoUrl.value = newData.anexo_url || '';
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
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>

