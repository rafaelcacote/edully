<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, FileText, Save, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NamedOption {
    id: string;
    nome: string;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    turmas: NamedOption[];
    alunos: NamedOption[];
    publicos: Option[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Financeiro', href: '/school/cobrancas' },
    { title: 'Eventos', href: '/school/cobrancas/eventos' },
    { title: 'Novo evento', href: '#' },
];

const publico = ref('turma');
const selectedAlunoIds = ref<string[]>([]);
const alunoSearch = ref('');
const boletoFile = ref<File | null>(null);
const boletoPreview = ref<string | null>(null);
const boletoInputRef = ref<HTMLInputElement | null>(null);

const showTurma = computed(() => publico.value === 'turma');
const showAlunos = computed(() => publico.value === 'alunos');

const filteredAlunos = computed(() => {
    const term = alunoSearch.value.trim().toLowerCase();
    if (!term) {
        return props.alunos;
    }

    return props.alunos.filter((aluno) => aluno.nome.toLowerCase().includes(term));
});

function toggleAluno(id: string) {
    if (selectedAlunoIds.value.includes(id)) {
        selectedAlunoIds.value = selectedAlunoIds.value.filter((item) => item !== id);
    } else {
        selectedAlunoIds.value = [...selectedAlunoIds.value, id];
    }
}

function handleBoletoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        boletoFile.value = target.files[0];
        boletoPreview.value = target.files[0].name;
    }
}

function removeBoleto() {
    boletoFile.value = null;
    boletoPreview.value = null;
    if (boletoInputRef.value) {
        boletoInputRef.value.value = '';
    }
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Novo evento financeiro" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Novo evento"
                        description="Crie um evento cobrável e publique para gerar as cobranças"
                        :icon="CalendarDays"
                    />
                </div>
                <Button variant="outline" as-child class="rounded-lg">
                    <Link href="/school/cobrancas/eventos" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    action="/school/cobrancas/eventos"
                    method="post"
                    enctype="multipart/form-data"
                    class="space-y-6"
                    #default="{ errors, processing }"
                >
                    <input
                        v-for="alunoId in selectedAlunoIds"
                        :key="alunoId"
                        type="hidden"
                        name="aluno_ids[]"
                        :value="alunoId"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="titulo">Título</Label>
                            <Input id="titulo" name="titulo" required placeholder="Ex.: Festa junina" />
                            <InputError :message="errors.titulo" />
                        </div>

                        <div class="space-y-2">
                            <Label for="valor">Valor (R$)</Label>
                            <Input id="valor" name="valor" type="number" step="0.01" min="0.01" required />
                            <InputError :message="errors.valor" />
                        </div>

                        <div class="space-y-2">
                            <Label for="vencimento">Vencimento</Label>
                            <Input id="vencimento" name="vencimento" type="date" required />
                            <InputError :message="errors.vencimento" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="descricao">Descrição</Label>
                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="errors.descricao" />
                        </div>

                        <div class="space-y-2">
                            <Label for="publico">Público</Label>
                            <select
                                id="publico"
                                v-model="publico"
                                name="publico"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="option in props.publicos"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="errors.publico" />
                        </div>

                        <div v-if="showTurma" class="space-y-2">
                            <Label for="turma_id">Turma</Label>
                            <select
                                id="turma_id"
                                name="turma_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selecione a turma</option>
                                <option
                                    v-for="turma in props.turmas"
                                    :key="turma.id"
                                    :value="turma.id"
                                >
                                    {{ turma.nome }}
                                </option>
                            </select>
                            <InputError :message="errors.turma_id" />
                        </div>

                        <div v-if="showAlunos" class="space-y-3 sm:col-span-2">
                            <Label>Alunos</Label>
                            <Input
                                v-model="alunoSearch"
                                placeholder="Buscar aluno..."
                            />
                            <div class="max-h-56 space-y-1 overflow-y-auto rounded-md border p-3">
                                <label
                                    v-for="aluno in filteredAlunos"
                                    :key="aluno.id"
                                    class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-muted/50"
                                >
                                    <input
                                        type="checkbox"
                                        class="rounded border-input"
                                        :checked="selectedAlunoIds.includes(aluno.id)"
                                        @change="toggleAluno(aluno.id)"
                                    />
                                    {{ aluno.nome }}
                                </label>
                                <p
                                    v-if="filteredAlunos.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    Nenhum aluno encontrado.
                                </p>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ selectedAlunoIds.length }} aluno(s) selecionado(s)
                            </p>
                            <InputError :message="errors.aluno_ids" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pix_chave">Chave PIX (opcional)</Label>
                            <Input id="pix_chave" name="pix_chave" />
                            <InputError :message="errors.pix_chave" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="pix_copia_cola">PIX copia e cola (opcional)</Label>
                            <textarea
                                id="pix_copia_cola"
                                name="pix_copia_cola"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="errors.pix_copia_cola" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="boleto">Boleto PDF (opcional)</Label>
                            <div
                                v-if="boletoPreview"
                                class="mb-2 flex items-center gap-3 rounded-md border p-3"
                            >
                                <FileText class="h-4 w-4" />
                                <span class="flex-1 text-sm">{{ boletoPreview }}</span>
                                <Button type="button" size="sm" variant="ghost" @click="removeBoleto">
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-4 py-3 text-sm text-muted-foreground hover:bg-muted/40"
                            >
                                <Upload class="h-4 w-4" />
                                Anexar boleto PDF
                                <input
                                    id="boleto"
                                    ref="boletoInputRef"
                                    type="file"
                                    name="boleto"
                                    accept="application/pdf"
                                    class="hidden"
                                    @change="handleBoletoChange"
                                />
                            </label>
                            <InputError :message="errors.boleto" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="publicar_agora"
                                    value="1"
                                    class="rounded border-input"
                                />
                                Publicar agora e gerar cobranças para o público selecionado
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="processing" class="gap-2">
                            <Save class="h-4 w-4" />
                            {{ processing ? 'Salvando...' : 'Salvar evento' }}
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
