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

/**
 * Converte ISO/datetime do backend para o formato de <input type="datetime-local">
 * sem passar por toISOString() (que desloca o horário para UTC).
 */
function toDateTimeLocalValue(value?: string | null): string {
    if (!value) {
        return '';
    }

    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
        return value;
    }

    const match = value.match(
        /^(\d{4}-\d{2}-\d{2})[T\s](\d{2}):(\d{2})/,
    );

    if (match) {
        return `${match[1]}T${match[2]}:${match[3]}`;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (n: number): string => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const prioridadeOptions = [
    { value: 'normal', label: 'Normal' },
    { value: 'media', label: 'Média' },
    { value: 'alta', label: 'Alta' },
];

const publicoAlvoOptions = [
    { value: 'todos', label: 'Toda a escola' },
    { value: 'alunos', label: 'Somente alunos' },
    { value: 'professores', label: 'Somente professores' },
    { value: 'responsaveis', label: 'Somente responsáveis' },
];

const statusPublicacaoOptions = [
    { value: '0', label: 'Rascunho' },
    { value: '1', label: 'Publicado' },
];

const publicado = ref(props.aviso?.publicado ? '1' : '0');
const publicadoEm = ref(toDateTimeLocalValue(props.aviso?.publicado_em));
const expiraEm = ref(toDateTimeLocalValue(props.aviso?.expira_em));
const conteudo = ref(props.aviso?.conteudo ?? '');
const anexoFile = ref<File | null>(null);
const anexoPreview = ref<string | null>(props.aviso?.anexo_url ?? null);

watch(
    () => props.aviso,
    (aviso) => {
        if (!aviso) {
            return;
        }

        publicado.value = aviso.publicado ? '1' : '0';
        publicadoEm.value = toDateTimeLocalValue(aviso.publicado_em);
        expiraEm.value = toDateTimeLocalValue(aviso.expira_em);
        conteudo.value = aviso.conteudo ?? '';
        anexoPreview.value = aviso.anexo_url ?? null;
    },
    { deep: true },
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
                placeholder="Ex: Comunicado importante sobre..."
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
                placeholder="Digite o conteúdo do comunicado..."
                rows="8"
                required
                class="flex min-h-[200px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            ></textarea>
            <InputError :message="errors.conteudo" />
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
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
                <p class="text-xs text-muted-foreground">
                    Define o destaque do comunicado na listagem.
                </p>
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
                <p class="text-xs text-muted-foreground">
                    Quem poderá visualizar este comunicado.
                </p>
                <InputError :message="errors.publico_alvo" />
            </div>

            <div class="grid gap-2">
                <Label for="publicado">Visibilidade</Label>
                <select
                    id="publicado"
                    name="publicado"
                    v-model="publicado"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option
                        v-for="option in statusPublicacaoOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <p class="text-xs text-muted-foreground">
                    Rascunho fica oculto. Publicado fica visível para o público-alvo.
                </p>
                <InputError :message="errors.publicado" />
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

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="publicado_em">Data de publicação</Label>
                <Input
                    id="publicado_em"
                    name="publicado_em"
                    type="datetime-local"
                    v-model="publicadoEm"
                />
                <p class="text-xs text-muted-foreground">
                    Quando o comunicado passa a valer. Se vazio e estiver publicado, usa a data atual.
                </p>
                <InputError :message="errors.publicado_em" />
            </div>

            <div class="grid gap-2">
                <Label for="expira_em">Data de expiração</Label>
                <Input
                    id="expira_em"
                    name="expira_em"
                    type="datetime-local"
                    v-model="expiraEm"
                />
                <p class="text-xs text-muted-foreground">
                    Opcional. Após esta data, o comunicado deixa de ser exibido.
                </p>
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
