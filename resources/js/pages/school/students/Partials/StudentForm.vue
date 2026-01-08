<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Turma {
    id: string;
    nome: string;
    serie?: string | null;
    turma_letra?: string | null;
    ano_letivo?: string | null;
}

interface Student {
    id?: string;
    nome?: string | null;
    nome_social?: string | null;
    foto_url?: string | null;
    data_nascimento?: string | null;
    ativo?: boolean;
    informacoes_medicas?: string | null;
}

const props = defineProps<{
    student?: Student;
    turmas?: Turma[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const fotoPreview = ref<string | null>(props.student?.foto_url ?? null);
const fotoFile = ref<File | null>(null);

function handleFotoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        fotoFile.value = file;

        // Criar preview
        const reader = new FileReader();
        reader.onload = (e) => {
            fotoPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
}

function removeFoto() {
    fotoPreview.value = null;
    fotoFile.value = null;
    const input = document.getElementById('foto') as HTMLInputElement;
    if (input) {
        input.value = '';
    }
}
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="nome">Nome completo</Label>
                <Input
                    id="nome"
                    name="nome"
                    :default-value="student?.nome ?? ''"
                    placeholder="Ex: João Silva Santos"
                    required
                />
                <InputError :message="errors.nome" />
            </div>

            <div class="grid gap-2">
                <Label for="nome_social">Nome social</Label>
                <Input
                    id="nome_social"
                    name="nome_social"
                    :default-value="student?.nome_social ?? ''"
                    placeholder="Ex: Maria"
                />
                <InputError :message="errors.nome_social" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="turma_id">Turma</Label>
            <select
                id="turma_id"
                name="turma_id"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                required
            >
                <option value="">Selecione uma turma</option>
                <option
                    v-for="turma in props.turmas"
                    :key="turma.id"
                    :value="turma.id"
                    :selected="student?.turma_id === turma.id"
                >
                    {{ turma.nome }}
                    <template v-if="turma.serie || turma.turma_letra">
                        ({{ [turma.serie, turma.turma_letra].filter(Boolean).join(' - ') }})
                    </template>
                    <template v-if="turma.ano_letivo">
                        - {{ turma.ano_letivo }}
                    </template>
                </option>
            </select>
            <InputError :message="errors.turma_id" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="data_nascimento">Data de nascimento</Label>
                <Input
                    id="data_nascimento"
                    name="data_nascimento"
                    type="date"
                    :default-value="student?.data_nascimento ?? ''"
                />
                <InputError :message="errors.data_nascimento" />
            </div>

            <div class="grid gap-2">
                <Label for="foto">Foto do aluno</Label>
                <div class="space-y-3">
                    <div v-if="fotoPreview" class="relative inline-block">
                        <img
                            :src="fotoPreview"
                            alt="Preview da foto"
                            class="h-32 w-32 rounded-lg object-cover border border-input"
                        />
                        <button
                            type="button"
                            @click="removeFoto"
                            class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <label
                            for="foto"
                            class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm hover:bg-accent"
                        >
                            <Upload class="h-4 w-4" />
                            <span>{{ fotoPreview ? 'Alterar foto' : 'Selecionar foto' }}</span>
                        </label>
                        <input
                            id="foto"
                            name="foto"
                            type="file"
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            class="hidden"
                            @change="handleFotoChange"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Formatos aceitos: JPEG, PNG, GIF, WebP. Tamanho máximo: 2MB.
                    </p>
                </div>
                <InputError :message="errors.foto" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="ativo">Status</Label>
            <label
                class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
            >
                <input
                    type="hidden"
                    name="ativo"
                    :value="student?.ativo === false ? '0' : '1'"
                />
                <input
                    id="ativo"
                    type="checkbox"
                    name="_ativo_toggle"
                    class="h-4 w-4 rounded border border-input"
                    :checked="student?.ativo !== false"
                    @change="
                        (e) => {
                            const checked = (e.target as HTMLInputElement).checked;
                            const hidden = (e.currentTarget as HTMLInputElement)
                                .closest('label')
                                ?.querySelector('input[type=hidden][name=ativo]') as HTMLInputElement | null;
                            if (hidden) hidden.value = checked ? '1' : '0';
                        }
                    "
                />
                <span class="text-muted-foreground">
                    {{ student?.ativo === false ? 'Inativo' : 'Ativo' }}
                </span>
            </label>
            <InputError :message="errors.ativo" />
        </div>

        <div class="grid gap-2">
            <Label for="informacoes_medicas">Informações médicas</Label>
            <textarea
                id="informacoes_medicas"
                name="informacoes_medicas"
                rows="3"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :default-value="student?.informacoes_medicas ?? ''"
                placeholder="Informações médicas relevantes (alergias, restrições, etc.)"
            />
            <InputError :message="errors.informacoes_medicas" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>

