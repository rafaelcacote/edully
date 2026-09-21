<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, FileText, Save, Upload, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Turma {
    id: string;
    nome: string;
    ano_letivo?: number | string | null;
}

interface Props {
    turmas: Turma[];
    defaults: {
        ano: number;
        mes: number;
        vencimento: string;
    };
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Financeiro',
        href: '/school/cobrancas',
    },
    {
        title: 'Gerar mensalidades',
        href: '/school/cobrancas/mensalidades/gerar',
    },
];

const meses = [
    { value: 1, label: 'Janeiro' },
    { value: 2, label: 'Fevereiro' },
    { value: 3, label: 'Março' },
    { value: 4, label: 'Abril' },
    { value: 5, label: 'Maio' },
    { value: 6, label: 'Junho' },
    { value: 7, label: 'Julho' },
    { value: 8, label: 'Agosto' },
    { value: 9, label: 'Setembro' },
    { value: 10, label: 'Outubro' },
    { value: 11, label: 'Novembro' },
    { value: 12, label: 'Dezembro' },
];

const boletoFile = ref<File | null>(null);
const boletoPreview = ref<string | null>(null);
const boletoInputRef = ref<HTMLInputElement | null>(null);
const valorDisplay = ref('');
const valorNumerico = ref('');

function formatCurrencyMask(digits: string): string {
    const cents = Number.parseInt(digits || '0', 10);

    return (cents / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function onValorInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const digits = target.value.replace(/\D/g, '').slice(0, 15);

    if (digits === '' || Number.parseInt(digits, 10) === 0) {
        valorDisplay.value = '';
        valorNumerico.value = '';
        target.value = '';

        return;
    }

    valorDisplay.value = formatCurrencyMask(digits);
    valorNumerico.value = (Number.parseInt(digits, 10) / 100).toFixed(2);
    target.value = valorDisplay.value;
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
        <Head title="Gerar mensalidades" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Gerar mensalidades"
                        description="Cria uma cobrança por aluno ativo da turma (ou de todas as turmas)"
                        :icon="CreditCard"
                    />
                </div>

                <Button variant="outline" as-child class="rounded-lg">
                    <Link href="/school/cobrancas" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    action="/school/cobrancas/mensalidades"
                    method="post"
                    enctype="multipart/form-data"
                    class="space-y-6"
                    #default="{ errors, processing }"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="turma_id">Turma</Label>
                            <select
                                id="turma_id"
                                name="turma_id"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Todas as turmas (alunos ativos)</option>
                                <option
                                    v-for="turma in props.turmas"
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

                        <div class="space-y-2">
                            <Label for="mes">Mês</Label>
                            <select
                                id="mes"
                                name="mes"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                required
                            >
                                <option
                                    v-for="mes in meses"
                                    :key="mes.value"
                                    :value="mes.value"
                                    :selected="mes.value === props.defaults.mes"
                                >
                                    {{ mes.label }}
                                </option>
                            </select>
                            <InputError :message="errors.mes" />
                        </div>

                        <div class="space-y-2">
                            <Label for="ano">Ano</Label>
                            <Input
                                id="ano"
                                name="ano"
                                type="number"
                                min="2000"
                                max="2100"
                                :default-value="props.defaults.ano"
                                required
                            />
                            <InputError :message="errors.ano" />
                        </div>

                        <div class="space-y-2">
                            <Label for="valor">Valor (R$)</Label>
                            <input
                                id="valor"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0,00"
                                :value="valorDisplay"
                                required
                                class="border-input bg-muted/60 selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground dark:bg-input/30 h-10 w-full min-w-0 rounded-lg border px-3 py-2 text-base shadow-sm outline-none transition-[color,box-shadow,background] focus-visible:border-ring focus-visible:bg-card focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                @input="onValorInput"
                            />
                            <input type="hidden" name="valor" :value="valorNumerico" />
                            <InputError :message="errors.valor" />
                        </div>

                        <div class="space-y-2">
                            <Label for="vencimento">Vencimento</Label>
                            <Input
                                id="vencimento"
                                name="vencimento"
                                type="date"
                                :default-value="props.defaults.vencimento"
                                required
                            />
                            <InputError :message="errors.vencimento" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="descricao">Descrição (opcional)</Label>
                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Ex.: Mensalidade referente ao mês letivo"
                            />
                            <InputError :message="errors.descricao" />
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
                            <Label for="boleto">Boleto PDF padrão (opcional)</Label>
                            <p class="text-xs text-muted-foreground">
                                Se enviado, o mesmo PDF será vinculado a todas as cobranças geradas.
                            </p>

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
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="submit" :disabled="processing" class="gap-2">
                            <Save class="h-4 w-4" />
                            {{ processing ? 'Gerando...' : 'Gerar mensalidades' }}
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
