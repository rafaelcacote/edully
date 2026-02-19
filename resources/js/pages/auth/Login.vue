<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { Check, School } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const cpfInput = ref('');
const selectedTenantId = ref('');
const tenants = ref<Array<{ id: string; name: string }>>([]);
const isAdminGeral = ref(false);
const isLoadingTenants = ref(false);
const showTenantSelection = ref(false);

function formatCPF(value: string): string {
    const numbers = value.replace(/\D/g, '');
    const limitedNumbers = numbers.slice(0, 11);
    if (limitedNumbers.length <= 3) {
        return limitedNumbers;
    } else if (limitedNumbers.length <= 6) {
        return `${limitedNumbers.slice(0, 3)}.${limitedNumbers.slice(3)}`;
    } else if (limitedNumbers.length <= 9) {
        return `${limitedNumbers.slice(0, 3)}.${limitedNumbers.slice(3, 6)}.${limitedNumbers.slice(6)}`;
    } else {
        return `${limitedNumbers.slice(0, 3)}.${limitedNumbers.slice(3, 6)}.${limitedNumbers.slice(6, 9)}-${limitedNumbers.slice(9, 11)}`;
    }
}

function handleCPFInput(value: string) {
    const numbers = value.replace(/\D/g, '');
    const limitedNumbers = numbers.slice(0, 11);
    cpfInput.value = formatCPF(limitedNumbers);
    
    // Buscar escolas quando o CPF tiver 11 dígitos
    if (limitedNumbers.length === 11) {
        fetchTenants(limitedNumbers);
    } else {
        // Limpar dados se CPF incompleto
        tenants.value = [];
        selectedTenantId.value = '';
        showTenantSelection.value = false;
        isAdminGeral.value = false;
    }
}

async function fetchTenants(cpf: string) {
    isLoadingTenants.value = true;
    try {
        const response = await fetch(`/api/auth/tenants-by-cpf?cpf=${encodeURIComponent(cpf)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (!response.ok) {
            throw new Error('Erro ao buscar escolas');
        }
        
        const data = await response.json();
        
        tenants.value = data.tenants || [];
        isAdminGeral.value = data.is_admin_geral || false;
        
        // Se for admin geral sem escolas, não mostrar seleção
        if (isAdminGeral.value && tenants.value.length === 0) {
            showTenantSelection.value = false;
            selectedTenantId.value = '';
            // Focar no campo de senha automaticamente
            await nextTick();
            setTimeout(() => {
                // Tentar múltiplas formas de encontrar o input
                let passwordInput = document.querySelector('#password[data-slot="input"]') as HTMLInputElement;
                if (!passwordInput) {
                    passwordInput = document.getElementById('password') as HTMLInputElement;
                }
                if (!passwordInput) {
                    passwordInput = document.querySelector('input[name="password"]') as HTMLInputElement;
                }
                if (passwordInput) {
                    passwordInput.focus();
                }
            }, 200);
        }
        // Se tiver apenas uma escola, selecionar automaticamente e não mostrar
        else if (tenants.value.length === 1) {
            selectedTenantId.value = tenants.value[0].id;
            showTenantSelection.value = false;
            // Focar no campo de senha automaticamente
            await nextTick();
            setTimeout(() => {
                // Tentar múltiplas formas de encontrar o input
                let passwordInput = document.querySelector('#password[data-slot="input"]') as HTMLInputElement;
                if (!passwordInput) {
                    passwordInput = document.getElementById('password') as HTMLInputElement;
                }
                if (!passwordInput) {
                    passwordInput = document.querySelector('input[name="password"]') as HTMLInputElement;
                }
                if (passwordInput) {
                    passwordInput.focus();
                }
            }, 200);
        }
        // Se tiver múltiplas escolas, mostrar seleção visual
        else if (tenants.value.length > 1) {
            showTenantSelection.value = true;
            selectedTenantId.value = '';
        }
        // Se não tiver escolas e não for admin geral, não mostrar
        else {
            showTenantSelection.value = false;
            selectedTenantId.value = '';
        }
    } catch (error) {
        tenants.value = [];
        selectedTenantId.value = '';
        showTenantSelection.value = false;
        isAdminGeral.value = false;
    } finally {
        isLoadingTenants.value = false;
    }
}

function selectTenant(tenantId: string) {
    selectedTenantId.value = tenantId;
    showTenantSelection.value = false;
    // Focar no campo de senha após seleção
    nextTick(() => {
        setTimeout(() => {
            // Tentar múltiplas formas de encontrar o input
            let passwordInput = document.querySelector('#password[data-slot="input"]') as HTMLInputElement;
            if (!passwordInput) {
                passwordInput = document.getElementById('password') as HTMLInputElement;
            }
            if (!passwordInput) {
                passwordInput = document.querySelector('input[name="password"]') as HTMLInputElement;
            }
            if (passwordInput) {
                passwordInput.focus();
            }
        }, 200);
    });
}

const isTenantRequired = computed(() => {
    return showTenantSelection.value && tenants.value.length > 1 && !selectedTenantId.value;
});

const selectedTenantName = computed(() => {
    if (!selectedTenantId.value || tenants.value.length === 0) {
        return '';
    }
    const tenant = tenants.value.find((t) => t.id === selectedTenantId.value);
    return tenant?.name || '';
});
</script>

<template>
    <AuthBase
        title="Acesse sua conta"
        description="Informe seu CPF e senha para entrar"
    >
        <Head title="Entrar" />

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="cpf">CPF</Label>
                    <div class="relative">
                        <Input
                            id="cpf"
                            type="text"
                            name="cpf"
                            v-model="cpfInput"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="username"
                            placeholder="000.000.000-00"
                            :disabled="isLoadingTenants"
                            @update:model-value="handleCPFInput"
                        />
                        <div
                            v-if="isLoadingTenants"
                            class="absolute right-3 top-1/2 -translate-y-1/2"
                        >
                            <Spinner class="h-4 w-4" />
                        </div>
                    </div>
                    <InputError :message="errors.cpf" />
                    <input
                        type="hidden"
                        name="cpf"
                        :value="cpfInput.replace(/\D/g, '')"
                    />
                </div>

                <!-- Campo hidden para garantir que o tenant_id seja sempre enviado quando houver um selecionado -->
                <input
                    v-if="selectedTenantId"
                    type="hidden"
                    name="tenant_id"
                    :value="selectedTenantId"
                />

                <!-- Seleção visual de escolas com cards -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <div
                        v-if="showTenantSelection && tenants.length > 1 && !isLoadingTenants"
                        class="grid gap-3"
                    >
                        <Label class="flex items-center gap-2 text-sm font-medium">
                            <School class="h-4 w-4" />
                            Selecione sua escola
                        </Label>
                        <div class="grid gap-2">
                            <button
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                type="button"
                                @click="selectTenant(tenant.id)"
                                :class="[
                                    'relative flex items-center gap-3 rounded-lg border-2 p-4 text-left transition-all hover:border-primary hover:bg-accent/50',
                                    selectedTenantId === tenant.id
                                        ? 'border-primary bg-accent'
                                        : 'border-input bg-background',
                                ]"
                            >
                                <div class="flex flex-1 items-center gap-3">
                                    <div
                                        :class="[
                                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                                            selectedTenantId === tenant.id
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <School class="h-5 w-5" />
                                    </div>
                                    <div class="flex-1">
                                        <p class="font-medium">{{ tenant.name }}</p>
                                    </div>
                                </div>
                                <div
                                    v-if="selectedTenantId === tenant.id"
                                    class="flex shrink-0 items-center justify-center"
                                >
                                    <Check class="h-5 w-5 text-primary" />
                                </div>
                            </button>
                        </div>
                        <InputError :message="errors.tenant_id" />
                    </div>
                </Transition>

                <!-- Indicador quando escola única foi selecionada automaticamente -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 translate-y-0"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <div
                        v-if="selectedTenantId && !showTenantSelection && selectedTenantName && tenants.length > 0"
                        class="flex items-center gap-2 rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm"
                    >
                        <School class="h-4 w-4 text-primary shrink-0" />
                        <span class="text-muted-foreground">
                            Escola: <span class="font-medium text-foreground">{{ selectedTenantName }}</span>
                        </span>
                    </div>
                </Transition>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Senha</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="showTenantSelection ? 5 : 3"
                        >
                            Esqueceu a senha?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="showTenantSelection ? 3 : 2"
                        autocomplete="current-password"
                        placeholder="Sua senha"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            :tabindex="showTenantSelection ? 4 : 3"
                        />
                        <span>Lembrar de mim</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="showTenantSelection ? 5 : 4"
                    :disabled="processing || isTenantRequired"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Entrar
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Não tem uma conta?
                <TextLink :href="register()" :tabindex="5">Criar conta</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
