<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save, Upload, FileText, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Aviso {
    id?: string;
    titulo?: string;
    conteudo?: string;
    prioridade?: string;
    publico_alvo?: string;
    anexo_url?: string;
    publicado?: boolean;
    publicado_em?: string;
    expira_em?: string;
}

const props = defineProps<{
    aviso?: Aviso;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const prioridadeOptions = [
    { value: 'normal', label: 'Normal' },
    { value: 'alta', label: 'Alta' },
    { value: 'media', label: 'Média' },
];

const publicoAlvoOptions = [
    { value: 'todos', label: 'Todos' },
    { value: 'alunos', label: 'Alunos' },
    { value: 'professores', label: 'Professores' },
    { value: 'responsaveis', label: 'Responsáveis' },
];

const publicado = ref(props.aviso?.publicado ?? false);
const publicadoEm = ref(
    props.aviso?.publicado_em
        ? new Date(props.aviso.publicado_em).toISOString().slice(0, 16)
        : '',
);
const expiraEm = ref(
    props.aviso?.expira_em
        ? new Date(props.aviso.expira_em).toISOString().slice(0, 16)
        : '',
);
const conteudo = ref(props.aviso?.conteudo ?? '');
const anexoFile = ref<File | null>(null);
const anexoPreview = ref<string | null>(props.aviso?.anexo_url ?? null);

// Watch para atualizar o hidden input quando publicado mudar
function updatePublicadoValue() {
    const hiddenInput = document.querySelector('input[name="publicado"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = publicado.value ? '1' : '0';
    }
}

// Watch para atualizar o conteudo quando as props mudarem
watch(
    () => props.aviso?.conteudo,
    (newValue) => {
        if (newValue !== undefined) {
            conteudo.value = newValue;
        }
    },
    { immediate: true }
);

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
    const input = document.querySelector('input[name="anexo"]') as HTMLInputElement;
    if (input) {
        input.value = '';
    }
}
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="titulo">Título</Label>
            <Input
                id="titulo"
                name="titulo"
                :default-value="aviso?.titulo ?? ''"
                placeholder="Ex: Aviso importante sobre..."
                required
            />
            <InputError :message="errors.titulo" />
        </div>

        <div class="grid gap-2">
            <Label for="conteudo">Conteúdo</Label>
            <textarea
                id="conteudo"
                name="conteudo"
                v-model="conteudo"
                placeholder="Digite o conteúdo do aviso..."
                rows="8"
                required
                class="flex min-h-[200px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            ></textarea>
            <InputError :message="errors.conteudo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="prioridade">Prioridade</Label>
                <select
                    id="prioridade"
                    name="prioridade"
                    :default-value="aviso?.prioridade ?? 'normal'"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option
                        v-for="option in prioridadeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.prioridade" />
            </div>

            <div class="grid gap-2">
                <Label for="publico_alvo">Público-alvo</Label>
                <select
                    id="publico_alvo"
                    name="publico_alvo"
                    :default-value="aviso?.publico_alvo ?? 'todos'"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option
                        v-for="option in publicoAlvoOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <InputError :message="errors.publico_alvo" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="anexo">Anexo (PDF)</Label>
            
            <div v-if="!anexoPreview && !anexoFile" class="space-y-2">
                <label
                    for="anexo"
                    class="flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm hover:bg-accent"
                >
                    <Upload class="h-4 w-4" />
                    <span>Selecionar arquivo PDF</span>
                </label>
                <input
                    id="anexo"
                    name="anexo"
                    type="file"
                    accept="application/pdf"
                    class="hidden"
                    @change="handleAnexoChange"
                />
                <p class="text-xs text-muted-foreground">
                    Formato aceito: PDF. Tamanho máximo: 10MB.
                </p>
            </div>

            <div v-else class="space-y-2">
                <div class="flex items-center gap-2 rounded-lg border border-input bg-muted/50 p-3">
                    <FileText class="h-5 w-5 text-muted-foreground" />
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            {{ anexoFile?.name || 'Anexo atual' }}
                        </p>
                        <p v-if="anexoPreview && !anexoFile" class="text-xs text-muted-foreground">
                            <a :href="anexoPreview" target="_blank" class="text-blue-500 hover:underline">
                                Ver anexo atual
                            </a>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="removeAnexo"
                        class="rounded p-1 hover:bg-destructive/10 hover:text-destructive"
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
                    accept="application/pdf"
                    class="hidden"
                    @change="handleAnexoChange"
                />
                <input
                    v-if="!anexoFile && !anexoPreview"
                    type="hidden"
                    name="anexo_url"
                    value=""
                />
            </div>


            <InputError :message="errors.anexo" />
            <InputError :message="errors.anexo_url" />
        </div>

        <div class="grid gap-2">
            <Label for="publicado">Status de Publicação</Label>
            <label
                class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
            >
                <input
                    type="hidden"
                    name="publicado"
                    :value="publicado ? '1' : '0'"
                />
                <input
                    id="publicado"
                    type="checkbox"
                    v-model="publicado"
                    @change="updatePublicadoValue"
                    class="h-4 w-4 rounded border border-input"
                />
                <span class="text-muted-foreground">
                    {{ publicado ? 'Publicado' : 'Não publicado' }}
                </span>
            </label>
            <InputError :message="errors.publicado" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="publicado_em">Data de Publicação</Label>
                <Input
                    id="publicado_em"
                    name="publicado_em"
                    type="datetime-local"
                    v-model="publicadoEm"
                />
                <InputError :message="errors.publicado_em" />
            </div>

            <div class="grid gap-2">
                <Label for="expira_em">Data de Expiração</Label>
                <Input
                    id="expira_em"
                    name="expira_em"
                    type="datetime-local"
                    v-model="expiraEm"
                />
                <InputError :message="errors.expira_em" />
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
