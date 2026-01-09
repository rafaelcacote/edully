<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface DisciplinaData {
    id?: string;
    nome?: string;
    sigla?: string | null;
    descricao?: string | null;
    carga_horaria_semanal?: number | null;
    ativo?: boolean;
}

const props = defineProps<{
    disciplinaData?: DisciplinaData;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const nome = ref(props.disciplinaData?.nome || '');
const sigla = ref(props.disciplinaData?.sigla || '');
const descricao = ref(props.disciplinaData?.descricao || '');
const cargaHorariaSemanal = ref(props.disciplinaData?.carga_horaria_semanal || '');
const ativo = ref(props.disciplinaData?.ativo !== false);

watch(() => props.disciplinaData, (newData) => {
    if (newData) {
        nome.value = newData.nome || '';
        sigla.value = newData.sigla || '';
        descricao.value = newData.descricao || '';
        cargaHorariaSemanal.value = newData.carga_horaria_semanal || '';
        ativo.value = newData.ativo !== false;
    }
}, { immediate: true, deep: true });
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="nome">Nome da disciplina</Label>
                <input
                    id="nome"
                    name="nome"
                    v-model="nome"
                    type="text"
                    placeholder="Ex: Matemática"
                    required
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-2">
                <Label for="sigla">Sigla</Label>
                <input
                    id="sigla"
                    name="sigla"
                    v-model="sigla"
                    type="text"
                    placeholder="Ex: MAT"
                    maxlength="20"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.sigla" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="descricao">Descrição</Label>
            <textarea
                id="descricao"
                name="descricao"
                v-model="descricao"
                rows="4"
                placeholder="Descreva a disciplina..."
                class="flex min-h-[80px] w-full rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
            />
            <InputError :message="errors.descricao" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="carga_horaria_semanal">Carga horária semanal (horas)</Label>
                <input
                    id="carga_horaria_semanal"
                    name="carga_horaria_semanal"
                    v-model="cargaHorariaSemanal"
                    type="number"
                    placeholder="Ex: 4"
                    min="1"
                    class="flex h-10 w-full min-w-0 rounded-lg border border-input bg-muted/60 px-3 py-2 text-base shadow-sm transition-[color,box-shadow,background] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:bg-card"
                />
                <InputError :message="errors.carga_horaria_semanal" />
            </div>

            <div class="grid gap-2">
                <Label for="ativo">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm cursor-pointer"
                >
                    <input
                        type="hidden"
                        name="ativo"
                        :value="ativo ? '1' : '0'"
                    />
                    <input
                        id="ativo"
                        type="checkbox"
                        v-model="ativo"
                        class="h-4 w-4 rounded border border-input cursor-pointer"
                    />
                    <span class="text-muted-foreground">
                        {{ ativo ? 'Ativa' : 'Inativa' }}
                    </span>
                </label>
                <InputError :message="errors.ativo" />
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

