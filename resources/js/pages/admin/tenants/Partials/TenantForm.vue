<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Tenant } from '@/types';
import { Save } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{
    tenant?: Tenant;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();

const phoneDisplay = ref('');
const cnpjDisplay = ref('');
const cepDisplay = ref('');
const logoPreview = ref<string | null>(null);
const logoFile = ref<File | null>(null);
const logoInputRef = ref<HTMLInputElement | null>(null);
const enderecoRef = ref<HTMLInputElement | null>(null);
const enderecoNumeroRef = ref<HTMLInputElement | null>(null);
const enderecoComplementoRef = ref<HTMLInputElement | null>(null);
const enderecoBairroRef = ref<HTMLInputElement | null>(null);
const enderecoCidadeRef = ref<HTMLInputElement | null>(null);
const enderecoEstadoRef = ref<HTMLInputElement | null>(null);
const enderecoPaisRef = ref<HTMLInputElement | null>(null);
const isLoadingCEP = ref(false);

function handleLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        // Validar tipo de arquivo
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione apenas arquivos de imagem.');
            if (logoInputRef.value) {
                logoInputRef.value.value = '';
            }
            return;
        }
        
        // Validar tamanho (máximo 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 5MB.');
            if (logoInputRef.value) {
                logoInputRef.value.value = '';
            }
            return;
        }
        
        logoFile.value = file;
        
        // Criar preview
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    logoFile.value = null;
    logoPreview.value = null;
    if (logoInputRef.value) {
        logoInputRef.value.value = '';
    }
}

function formatCNPJ(value: string): string {
    // Remove tudo que não é número
    const numbers = value.replace(/\D/g, '');

    // Limita a 14 dígitos
    const limitedNumbers = numbers.slice(0, 14);

    // Aplica a máscara: XX.XXX.XXX/XXXX-XX
    if (limitedNumbers.length <= 2) {
        return limitedNumbers;
    } else if (limitedNumbers.length <= 5) {
        return `${limitedNumbers.slice(0, 2)}.${limitedNumbers.slice(2)}`;
    } else if (limitedNumbers.length <= 8) {
        return `${limitedNumbers.slice(0, 2)}.${limitedNumbers.slice(2, 5)}.${limitedNumbers.slice(5)}`;
    } else if (limitedNumbers.length <= 12) {
        return `${limitedNumbers.slice(0, 2)}.${limitedNumbers.slice(2, 5)}.${limitedNumbers.slice(5, 8)}/${limitedNumbers.slice(8)}`;
    } else {
        return `${limitedNumbers.slice(0, 2)}.${limitedNumbers.slice(2, 5)}.${limitedNumbers.slice(5, 8)}/${limitedNumbers.slice(8, 12)}-${limitedNumbers.slice(12, 14)}`;
    }
}

function handleCNPJInput(value: string) {
    // Remove caracteres não numéricos
    const numbers = value.replace(/\D/g, '');
    
    // Limita a 14 dígitos
    const limitedNumbers = numbers.slice(0, 14);
    
    // Aplica a máscara
    cnpjDisplay.value = formatCNPJ(limitedNumbers);
    
    // Atualiza o campo hidden com o valor sem máscara
    const hiddenInput = document.querySelector('input[name="cnpj"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
}

function formatPhone(value: string): string {
    // Remove tudo que não é número
    const numbers = value.replace(/\D/g, '');

    // Aplica a máscara baseada no tamanho
    if (numbers.length <= 2) {
        return numbers.length > 0 ? `(${numbers}` : '';
    } else if (numbers.length <= 6) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2)}`;
    } else if (numbers.length <= 10) {
        // Telefone fixo: (XX) XXXX-XXXX
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    } else {
        // Telefone celular: (XX) XXXXX-XXXX
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7, 11)}`;
    }
}

function handlePhoneInput(value: string) {
    // Remove caracteres não numéricos
    const numbers = value.replace(/\D/g, '');
    
    // Limita a 11 dígitos (celular)
    const limitedNumbers = numbers.slice(0, 11);
    
    // Aplica a máscara
    phoneDisplay.value = formatPhone(limitedNumbers);
    
    // Atualiza o campo hidden com o valor sem máscara
    const hiddenInput = document.querySelector('input[name="telefone"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
}

function formatCEP(value: string): string {
    // Remove tudo que não é número
    const numbers = value.replace(/\D/g, '');
    
    // Limita a 8 dígitos
    const limitedNumbers = numbers.slice(0, 8);
    
    // Aplica a máscara: XXXXX-XXX
    if (limitedNumbers.length <= 5) {
        return limitedNumbers;
    } else {
        return `${limitedNumbers.slice(0, 5)}-${limitedNumbers.slice(5, 8)}`;
    }
}

async function handleCEPInput(value: string) {
    // Remove caracteres não numéricos
    const numbers = value.replace(/\D/g, '');
    
    // Limita a 8 dígitos
    const limitedNumbers = numbers.slice(0, 8);
    
    // Aplica a máscara
    cepDisplay.value = formatCEP(limitedNumbers);
    
    // Atualiza o campo hidden com o valor sem máscara
    const hiddenInput = document.querySelector('input[name="endereco_cep"][type="hidden"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
    
    // Buscar CEP quando tiver 8 dígitos
    if (limitedNumbers.length === 8) {
        await buscarCEP(limitedNumbers);
    }
}

async function buscarCEP(cep: string) {
    if (!cep || cep.length !== 8) return;
    
    isLoadingCEP.value = true;
    
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();
        
        if (!data.erro) {
            // Preencher campos automaticamente usando os elementos do DOM
            const enderecoInput = document.querySelector('input[name="endereco"]') as HTMLInputElement;
            const bairroInput = document.querySelector('input[name="endereco_bairro"]') as HTMLInputElement;
            const cidadeInput = document.querySelector('input[name="endereco_cidade"]') as HTMLInputElement;
            const estadoInput = document.querySelector('input[name="endereco_estado"]') as HTMLInputElement;
            const paisInput = document.querySelector('input[name="endereco_pais"]') as HTMLInputElement;
            
            if (enderecoInput) {
                enderecoInput.value = data.logradouro || '';
                // Disparar evento para garantir que o valor seja atualizado
                enderecoInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (bairroInput) {
                bairroInput.value = data.bairro || '';
                bairroInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (cidadeInput) {
                cidadeInput.value = data.localidade || '';
                cidadeInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (estadoInput) {
                estadoInput.value = (data.uf || '').toUpperCase();
                estadoInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (paisInput) {
                paisInput.value = 'Brasil';
                paisInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            
            // Focar no campo número após buscar CEP
            setTimeout(() => {
                const numeroInput = document.querySelector('input[name="endereco_numero"]') as HTMLInputElement;
                if (numeroInput) {
                    numeroInput.focus();
                }
            }, 150);
        } else {
            // CEP não encontrado, ainda foca no número
            setTimeout(() => {
                const numeroInput = document.querySelector('input[name="endereco_numero"]') as HTMLInputElement;
                if (numeroInput) {
                    numeroInput.focus();
                }
            }, 150);
        }
    } catch (error) {
        console.error('Erro ao buscar CEP:', error);
        // Mesmo com erro, focar no número
        setTimeout(() => {
            const numeroInput = document.querySelector('input[name="endereco_numero"]') as HTMLInputElement;
            if (numeroInput) {
                numeroInput.focus();
            }
        }, 150);
    } finally {
        isLoadingCEP.value = false;
    }
}

onMounted(() => {
    if (props.tenant?.phone) {
        phoneDisplay.value = formatPhone(props.tenant.phone);
    }
    if (props.tenant?.cnpj) {
        cnpjDisplay.value = formatCNPJ(props.tenant.cnpj);
    }
    if (props.tenant?.endereco_cep) {
        cepDisplay.value = formatCEP(props.tenant.endereco_cep);
    }
    if (props.tenant?.logo_url) {
        logoPreview.value = props.tenant.logo_url;
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="nome">Nome da Escola</Label>
            <Input
                id="nome"
                name="nome"
                :default-value="tenant?.name ?? ''"
                placeholder="Ex: Escola Municipal João Silva"
                required
            />
            <InputError :message="errors.nome" />
        </div>

        <div class="grid gap-6 sm:grid-cols-4">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="tenant?.email ?? ''"
                    placeholder="contato@escola.com"
                    required
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="subdominio">Subdomínio</Label>
                <Input
                    id="subdominio"
                    name="subdominio"
                    :default-value="tenant?.subdomain ?? ''"
                    placeholder="escola-joao-silva"
                    autocomplete="off"
                />
                <InputError :message="errors.subdominio" />
            </div>

            <div class="grid gap-2">
                <Label for="cnpj">CNPJ</Label>
                <div class="relative">
                    <Input
                        id="cnpj"
                        :model-value="cnpjDisplay"
                        placeholder="00.000.000/0000-00"
                        autocomplete="off"
                        @update:model-value="handleCNPJInput"
                    />
                    <input
                        type="hidden"
                        name="cnpj"
                        :value="cnpjDisplay.replace(/\D/g, '')"
                    />
                </div>
                <InputError :message="errors.cnpj" />
            </div>

            <div class="grid gap-2">
                <Label for="telefone">Telefone</Label>
                <div class="relative">
                    <Input
                        id="telefone"
                        :model-value="phoneDisplay"
                        placeholder="(11) 99999-9999"
                        autocomplete="tel"
                        @update:model-value="handlePhoneInput"
                    />
                    <input
                        type="hidden"
                        name="telefone"
                        :value="phoneDisplay.replace(/\D/g, '')"
                    />
                </div>
                <InputError :message="errors.telefone" />
            </div>
        </div>

        <div class="space-y-4">
            <div>
                <h3 class="mb-4 text-base font-semibold">Endereço</h3>
            </div>
            
            <!-- Linha 1: CEP, Logradouro, Número, Complemento -->
            <div class="grid gap-6 sm:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="endereco_cep">CEP</Label>
                    <div class="relative">
                        <Input
                            id="endereco_cep"
                            :model-value="cepDisplay"
                            placeholder="00000-000"
                            autocomplete="postal-code"
                            :disabled="isLoadingCEP"
                            @update:model-value="handleCEPInput"
                        />
                        <input
                            type="hidden"
                            name="endereco_cep"
                            :value="cepDisplay.replace(/\D/g, '')"
                        />
                        <span
                            v-if="isLoadingCEP"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"
                        >
                            Buscando...
                        </span>
                    </div>
                    <InputError :message="errors.endereco_cep" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco">Logradouro</Label>
                    <Input
                        ref="enderecoRef"
                        id="endereco"
                        name="endereco"
                        :default-value="tenant?.address ?? ''"
                        placeholder="Rua, Avenida, etc."
                    />
                    <InputError :message="errors.endereco" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco_numero">Número</Label>
                    <Input
                        ref="enderecoNumeroRef"
                        id="endereco_numero"
                        name="endereco_numero"
                        :default-value="tenant?.endereco_numero ?? ''"
                        placeholder="123"
                    />
                    <InputError :message="errors.endereco_numero" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco_complemento">Complemento</Label>
                    <Input
                        ref="enderecoComplementoRef"
                        id="endereco_complemento"
                        name="endereco_complemento"
                        :default-value="tenant?.endereco_complemento ?? ''"
                        placeholder="Apto, Bloco, etc."
                    />
                    <InputError :message="errors.endereco_complemento" />
                </div>
            </div>

            <!-- Linha 3: Bairro, Cidade, Estado, País -->
            <div class="grid gap-6 sm:grid-cols-4">
                <div class="grid gap-2">
                    <Label for="endereco_bairro">Bairro</Label>
                    <Input
                        ref="enderecoBairroRef"
                        id="endereco_bairro"
                        name="endereco_bairro"
                        :default-value="tenant?.endereco_bairro ?? ''"
                        placeholder="Nome do bairro"
                    />
                    <InputError :message="errors.endereco_bairro" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco_cidade">Cidade</Label>
                    <Input
                        ref="enderecoCidadeRef"
                        id="endereco_cidade"
                        name="endereco_cidade"
                        :default-value="tenant?.endereco_cidade ?? ''"
                        placeholder="Nome da cidade"
                    />
                    <InputError :message="errors.endereco_cidade" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco_estado">Estado (UF)</Label>
                    <Input
                        ref="enderecoEstadoRef"
                        id="endereco_estado"
                        name="endereco_estado"
                        :default-value="tenant?.endereco_estado ?? ''"
                        placeholder="SP"
                        maxlength="2"
                        class="uppercase"
                        @input="(e) => {
                            const target = e.target as HTMLInputElement;
                            target.value = target.value.toUpperCase();
                        }"
                    />
                    <InputError :message="errors.endereco_estado" />
                </div>

                <div class="grid gap-2">
                    <Label for="endereco_pais">País</Label>
                    <Input
                        ref="enderecoPaisRef"
                        id="endereco_pais"
                        name="endereco_pais"
                        :default-value="tenant?.endereco_pais ?? 'Brasil'"
                        placeholder="Brasil"
                    />
                    <InputError :message="errors.endereco_pais" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="logo">Logo</Label>
                <div class="flex items-start gap-4">
                    <div v-if="logoPreview" class="relative flex-shrink-0">
                        <img
                            :src="logoPreview"
                            alt="Preview da logo"
                            class="h-24 w-24 rounded-lg border object-contain"
                        />
                        <button
                            type="button"
                            @click="removeLogo"
                            class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1">
                        <input
                            ref="logoInputRef"
                            id="logo"
                            name="logo"
                            type="file"
                            accept="image/*"
                            @change="handleLogoChange"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 5MB
                        </p>
                    </div>
                </div>
                <InputError :message="errors.logo" />
                <InputError :message="errors.logo_url" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="ativo">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <input
                        type="hidden"
                        name="ativo"
                        :value="tenant?.is_active === false ? '0' : '1'"
                    />
                    <input
                        id="ativo"
                        type="checkbox"
                        name="_ativo_toggle"
                        class="h-4 w-4 rounded border border-input"
                        :checked="tenant?.is_active !== false"
                        @change="
                            (e) => {
                                const checked = (e.target as HTMLInputElement)
                                    .checked;
                                const hidden = (
                                    e.currentTarget as HTMLInputElement
                                )
                                    .closest('label')
                                    ?.querySelector(
                                        'input[type=hidden][name=ativo]',
                                    ) as HTMLInputElement | null;
                                if (hidden) hidden.value = checked ? '1' : '0';
                            }
                        "
                    />
                    <span class="text-muted-foreground"
                        >{{ tenant?.is_active === false ? 'Inativo' : 'Ativo' }}</span
                    >
                </label>
                <InputError :message="errors.ativo" />
            </div>

            <div class="grid gap-2">
                <Label for="trial_ate">Trial até</Label>
                <Input
                    id="trial_ate"
                    name="trial_ate"
                    type="date"
                    :default-value="tenant?.trial_until ? new Date(tenant.trial_until).toISOString().split('T')[0] : ''"
                />
                <InputError :message="errors.trial_ate" />
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

