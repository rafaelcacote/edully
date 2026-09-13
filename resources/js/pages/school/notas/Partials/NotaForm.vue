<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface NotaData {
    id?: string;
    aluno_id?: string;
    professor_id?: string;
    turma_id?: string | null;
    disciplina_id?: string | null;
    bimestre?: number;
    nota?: number;
    comportamento?: string | null;
    observacoes?: string | null;
    ano_letivo?: number;
}

interface Aluno {
    id: string;
    nome: string;
}

interface Professor {
    id: string;
    nome_completo: string;
}

interface Turma {
    id: string;
    nome: string;
    ano_letivo?: number | null;
}

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

const props = withDefaults(defineProps<{
    notaData?: NotaData;
    alunos?: Aluno[];
    professores?: Professor[];
    turmas?: Turma[];
    grade?: Record<string, Disciplina[]>;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>(), {
    alunos: () => [],
    professores: () => [],
    turmas: () => [],
    grade: () => ({}),
    errors: () => ({}),
});

const alunoId = ref(props.notaData?.aluno_id || '');
const professorId = ref(props.notaData?.professor_id || '');
const turmaId = ref(props.notaData?.turma_id || '');
const disciplinaId = ref(props.notaData?.disciplina_id || '');
const bimestre = ref(props.notaData?.bimestre?.toString() || '');
const nota = ref(props.notaData?.nota?.toString() || '');
const comportamento = ref(props.notaData?.comportamento || '');
const observacoes = ref(props.notaData?.observacoes || '');
const anoLetivo = ref(props.notaData?.ano_letivo?.toString() || new Date().getFullYear().toString());

const alunosList = computed(() => props.alunos || []);
const professoresList = computed(() => props.professores || []);
const turmasList = computed(() => props.turmas || []);

const disciplinasDaTurma = computed(() => {
    if (!turmaId.value) {
        return [] as Disciplina[];
    }

    return props.grade?.[turmaId.value] || [];
});

const comportamentoOptions = [
    { value: '', label: 'Selecione' },
    { value: 'excelente', label: 'Excelente' },
    { value: 'bom', label: 'Bom' },
    { value: 'regular', label: 'Regular' },
    { value: 'ruim', label: 'Ruim' },
];

watch(turmaId, (newTurmaId, oldTurmaId) => {
    if (oldTurmaId && newTurmaId !== oldTurmaId) {
        disciplinaId.value = '';
    }

    const turma = turmasList.value.find((item) => item.id === newTurmaId);
    if (turma?.ano_letivo) {
        anoLetivo.value = String(turma.ano_letivo);
    }
});

watch(() => props.notaData, (newData) => {
    if (newData) {
        alunoId.value = newData.aluno_id || '';
        professorId.value = newData.professor_id || '';
        turmaId.value = newData.turma_id || '';
        disciplinaId.value = newData.disciplina_id || '';
        bimestre.value = newData.bimestre?.toString() || '';
        nota.value = newData.nota?.toString() || '';
        comportamento.value = newData.comportamento || '';
        observacoes.value = newData.observacoes || '';
        anoLetivo.value = newData.ano_letivo?.toString() || new Date().getFullYear().toString();
    }
}, { immediate: true, deep: true });
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
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
                        v-for="turma in turmasList"
                        :key="turma.id"
                        :value="turma.id"
                    >
                        {{ turma.nome }}
                        <template v-if="turma.ano_letivo">
                            ({{ turma.ano_letivo }})
                        </template>
                    </option>
                </select>
                <InputError :message="errors.turma_id" />
            </div>

            <div class="grid gap-2">
                <Label for="ano_letivo">Ano Letivo</Label>
                <input
                    id="ano_letivo"
                    name="ano_letivo"
                    v-model="anoLetivo"
                    type="number"
                    placeholder="Ex: 2026"
                    min="2000"
                    max="2100"
                    required
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.ano_letivo" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="aluno_id">Aluno</Label>
                <select
                    id="aluno_id"
                    name="aluno_id"
                    v-model="alunoId"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione um aluno</option>
                    <option
                        v-for="aluno in alunosList"
                        :key="aluno.id"
                        :value="aluno.id"
                    >
                        {{ aluno.nome }}
                    </option>
                </select>
                <InputError :message="errors.aluno_id" />
            </div>

            <div class="grid gap-2">
                <Label for="professor_id">Professor</Label>
                <select
                    id="professor_id"
                    name="professor_id"
                    v-model="professorId"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione um professor</option>
                    <option
                        v-for="professor in professoresList"
                        :key="professor.id"
                        :value="professor.id"
                    >
                        {{ professor.nome_completo }}
                    </option>
                </select>
                <InputError :message="errors.professor_id" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="disciplina_id">Disciplina</Label>
                <select
                    id="disciplina_id"
                    name="disciplina_id"
                    v-model="disciplinaId"
                    required
                    :disabled="!turmaId"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">
                        {{ turmaId ? 'Selecione uma disciplina' : 'Selecione a turma primeiro' }}
                    </option>
                    <option
                        v-for="disciplinaItem in disciplinasDaTurma"
                        :key="disciplinaItem.id"
                        :value="disciplinaItem.id"
                    >
                        {{ disciplinaItem.nome }}{{ disciplinaItem.sigla ? ` (${disciplinaItem.sigla})` : '' }}
                    </option>
                </select>
                <p
                    v-if="turmaId && disciplinasDaTurma.length === 0"
                    class="text-xs text-muted-foreground"
                >
                    Esta turma ainda não tem grade curricular. Vincule disciplinas na turma antes de lançar notas.
                </p>
                <InputError :message="errors.disciplina_id" />
            </div>

            <div class="grid gap-2">
                <Label for="bimestre">Bimestre</Label>
                <select
                    id="bimestre"
                    name="bimestre"
                    v-model="bimestre"
                    required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione o bimestre</option>
                    <option value="1">1º Bimestre</option>
                    <option value="2">2º Bimestre</option>
                    <option value="3">3º Bimestre</option>
                    <option value="4">4º Bimestre</option>
                </select>
                <InputError :message="errors.bimestre" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="nota">Nota (0 a 10)</Label>
                <input
                    id="nota"
                    name="nota"
                    v-model="nota"
                    type="number"
                    placeholder="Ex: 8.5"
                    min="0"
                    max="10"
                    step="0.1"
                    required
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.nota" />
            </div>

            <div class="grid gap-2">
                <Label for="comportamento">Comportamento (opcional)</Label>
                <select
                    id="comportamento"
                    name="comportamento"
                    v-model="comportamento"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option
                        v-for="option in comportamentoOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.comportamento" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="observacoes">Observações (opcional)</Label>
            <textarea
                id="observacoes"
                name="observacoes"
                v-model="observacoes"
                rows="4"
                placeholder="Adicione observações sobre a nota..."
                class="flex min-h-[80px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.observacoes" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
