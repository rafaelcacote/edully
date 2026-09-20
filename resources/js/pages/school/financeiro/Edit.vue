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

interface Cobranca {
    id: string;
    titulo: string;
    descricao: string | null;
    valor: string | number;
    vencimento: string | null;
    aluno: { id: string; nome: string } | null;
    boleto_url: string | null;
    pix_copia_cola: string | null;
    pix_chave: string | null;
}

interface Props {
    cobranca: Cobranca;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Financeiro',
        href: '/school/cobrancas',
    },
    {
        title: props.cobranca.titulo,
        href: `/school/cobrancas/${props.cobranca.id}`,
    },
    {
        title: 'Editar',
        href: `#`,
    },
];

const boletoFile = ref<File | null>(null);
const boletoPreview = ref<string | null>(null);
const boletoInputRef = ref<HTMLInputElement | null>(null);
const removerBoleto = ref(false);

function handleBoletoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        boletoFile.value = target.files[0];
        boletoPreview.value = target.files[0].name;
        removerBoleto.value = false;
    }
}

function removeBoletoSelecionado() {
    boletoFile.value = null;
    boletoPreview.value = null;
    if (boletoInputRef.value) {
        boletoInputRef.value.value = '';
    }
}

function marcarRemoverBoleto() {
    removerBoleto.value = true;
    removeBoletoSelecionado();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar: ${props.cobranca.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Editar cobrança"
                        :description="`Aluno: ${props.cobranca.aluno?.nome || '—'}`"
                        :icon="CreditCard"
                    />
                </div>

                <Button variant="outline" as-child class="rounded-lg">
                    <Link
                        :href="`/school/cobrancas/${props.cobranca.id}`"
                        class="flex items-center gap-2"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/cobrancas/${props.cobranca.id}`"
                    method="post"
                    enctype="multipart/form-data"
                    class="space-y-6"
                    #default="{ errors, processing }"
                >
                    <input type="hidden" name="_method" value="patch" />
                    <input
                        type="hidden"
                        name="remover_boleto"
                        :value="removerBoleto ? '1' : '0'"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="titulo">Título</Label>
                            <Input
                                id="titulo"
                                name="titulo"
                                :default-value="props.cobranca.titulo"
                                required
                            />
                            <InputError :message="errors.titulo" />
                        </div>

                        <div class="space-y-2">
                            <Label for="valor">Valor (R$)</Label>
                            <Input
                                id="valor"
                                name="valor"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :default-value="props.cobranca.valor"
                                required
                            />
                            <InputError :message="errors.valor" />
                        </div>

                        <div class="space-y-2">
                            <Label for="vencimento">Vencimento</Label>
                            <Input
                                id="vencimento"
                                name="vencimento"
                                type="date"
                                :default-value="props.cobranca.vencimento ?? ''"
                                required
                            />
                            <InputError :message="errors.vencimento" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="descricao">Descrição</Label>
                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :value="props.cobranca.descricao ?? ''"
                            />
                            <InputError :message="errors.descricao" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pix_chave">Chave PIX</Label>
                            <Input
                                id="pix_chave"
                                name="pix_chave"
                                :default-value="props.cobranca.pix_chave ?? ''"
                            />
                            <InputError :message="errors.pix_chave" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="pix_copia_cola">PIX copia e cola</Label>
                            <textarea
                                id="pix_copia_cola"
                                name="pix_copia_cola"
                                rows="3"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                :value="props.cobranca.pix_copia_cola ?? ''"
                            />
                            <InputError :message="errors.pix_copia_cola" />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="boleto">Boleto (PDF)</Label>

                            <div
                                v-if="props.cobranca.boleto_url && !removerBoleto && !boletoFile"
                                class="mb-2 flex items-center gap-3 rounded-md border p-3"
                            >
                                <FileText class="h-4 w-4 text-blue-500" />
                                <a
                                    :href="props.cobranca.boleto_url"
                                    target="_blank"
                                    class="flex-1 text-sm text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    Boleto atual
                                </a>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    @click="marcarRemoverBoleto"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>

                            <div
                                v-if="boletoPreview"
                                class="mb-2 flex items-center gap-3 rounded-md border p-3"
                            >
                                <FileText class="h-4 w-4" />
                                <span class="flex-1 text-sm">{{ boletoPreview }}</span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    @click="removeBoletoSelecionado"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>

                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-4 py-3 text-sm text-muted-foreground hover:bg-muted/40"
                            >
                                <Upload class="h-4 w-4" />
                                {{
                                    props.cobranca.boleto_url && !removerBoleto
                                        ? 'Substituir boleto PDF'
                                        : 'Enviar boleto PDF'
                                }}
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
                            {{ processing ? 'Salvando...' : 'Salvar' }}
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
