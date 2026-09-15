<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePage } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Parent {
    id?: string;
    usuario_id?: string;
    nome_completo?: string;
    cpf?: string | null;
    data_nascimento?: string | null;
    telefone?: string | null;
    email?: string | null;
    endereco?: string | null;
    endereco_numero?: string | null;
    endereco_complemento?: string | null;
    endereco_bairro?: string | null;
    endereco_cep?: string | null;
    endereco_cidade?: string | null;
    endereco_estado?: string | null;
    endereco_pais?: string | null;
    parentesco?: string | null;
    profissao?: string | null;
    ativo?: boolean;
    observacoes?: string | null;
}

const props = defineProps<{
    parent?: Parent;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
    editMode?: boolean;
}>();

const parentescoOptions: string[] = [
    'Pai',
    'Mãe',
    'Avô',
    'Avó',
    'Tio',
    'Tia',
    'Padrasto',
    'Madrasta',
    'Irmão',
    'Irmã',
    'Tutor(a) legal',
    'Responsável legal',
    'Outro',
];

const phoneDisplay = ref('');
const cpfDisplay = ref('');
const cpfError = ref<string | null>(null);
const cpfValidating = ref(false);
const cpfValid = ref<boolean | null>(null);
const cpfExists = ref(false);
const emailDisplay = ref(props.parent?.email ?? '');
const emailError = ref<string | null>(null);
const emailValidating = ref(false);
const emailExists = ref(false);
const dataNascimento = ref(props.parent?.data_nascimento ?? '');
const observacoes = ref(props.parent?.observacoes ?? '');
let emailCheckTimeout: ReturnType<typeof setTimeout> | null = null;

function validateCpf(cpf: string): boolean {
    const numbers = cpf.replace(/\D/g, '');

    if (numbers.length !== 11) {
        return false;
    }

    if (/^(\d)\1{10}$/.test(numbers)) {
        return false;
    }

    let sum = 0;
    for (let i = 0; i < 9; i++) {
        sum += parseInt(numbers[i]) * (10 - i);
    }
    let digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(numbers[9])) {
        return false;
    }

    sum = 0;
    for (let i = 0; i < 10; i++) {
        sum += parseInt(numbers[i]) * (11 - i);
    }
    digit = 11 - (sum % 11);
    if (digit >= 10) digit = 0;
    if (digit !== parseInt(numbers[10])) {
        return false;
    }

    return true;
}

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

async function handleCPFInput(value: string | number) {
    const numbers = String(value).replace(/\D/g, '');
    const limitedNumbers = numbers.slice(0, 11);

    const currentNumbers = cpfDisplay.value.replace(/\D/g, '');
    if (currentNumbers.length >= 11 && numbers.length > currentNumbers.length) {
        return;
    }

    cpfDisplay.value = formatCPF(limitedNumbers);
    const hiddenInput = document.querySelector('input[name="cpf"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }

    cpfError.value = null;
    cpfValid.value = null;
    cpfExists.value = false;

    if (limitedNumbers.length === 11 && !props.editMode) {
        cpfValidating.value = true;

        const isValid = validateCpf(limitedNumbers);
        cpfValid.value = isValid;

        if (!isValid) {
            cpfError.value = 'CPF inválido';
            cpfValidating.value = false;
            return;
        }

        checkCpfWithFetch(limitedNumbers);
    } else if (limitedNumbers.length > 0 && limitedNumbers.length < 11 && !props.editMode) {
        cpfError.value = null;
        cpfValid.value = null;
    }
}

function getCookie(name: string): string {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        return parts.pop()?.split(';').shift() || '';
    }
    return '';
}

async function checkCpfWithFetch(cpf: string) {
    try {
        const page = usePage();
        const csrfToken = (page.props as any).csrfToken || getCookie('XSRF-TOKEN');

        const response = await fetch('/school/parents/check-cpf', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ cpf }),
        });

        if (!response.ok) {
            throw new Error('Erro ao verificar CPF');
        }

        const data = await response.json();

        if (data.exists) {
            cpfExists.value = true;
            cpfError.value = 'Este CPF já está cadastrado no sistema e não pode ser utilizado novamente.';
        } else {
            cpfExists.value = false;
            cpfError.value = null;
        }
    } catch (error) {
        console.error('Error checking CPF:', error);
        cpfError.value = 'Erro ao verificar CPF';
    } finally {
        cpfValidating.value = false;
    }
}

function isValidEmailFormat(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function handleEmailInput(value: string | number) {
    const email = String(value).trim();
    emailDisplay.value = email;
    emailError.value = null;
    emailExists.value = false;

    if (emailCheckTimeout) {
        clearTimeout(emailCheckTimeout);
    }

    if (!email) {
        emailValidating.value = false;
        return;
    }

    if (!isValidEmailFormat(email)) {
        return;
    }

    emailCheckTimeout = setTimeout(() => {
        checkEmailWithFetch(email);
    }, 400);
}

async function checkEmailWithFetch(email: string) {
    emailValidating.value = true;

    try {
        const page = usePage();
        const csrfToken = (page.props as any).csrfToken || getCookie('XSRF-TOKEN');

        const response = await fetch('/school/parents/check-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email,
                ignore_user_id: props.parent?.usuario_id ?? null,
            }),
        });

        if (!response.ok) {
            throw new Error('Erro ao verificar e-mail');
        }

        const data = await response.json();

        if (data.exists) {
            emailExists.value = true;
            emailError.value =
                'Este e-mail já está cadastrado no sistema e não pode ser utilizado novamente.';
        } else {
            emailExists.value = false;
            emailError.value = null;
        }
    } catch (error) {
        console.error('Error checking email:', error);
        emailError.value = 'Erro ao verificar e-mail';
    } finally {
        emailValidating.value = false;
    }
}

function formatPhone(value: string): string {
    const numbers = value.replace(/\D/g, '');
    if (numbers.length <= 2) {
        return numbers.length > 0 ? `(${numbers}` : '';
    } else if (numbers.length <= 6) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2)}`;
    } else if (numbers.length <= 10) {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 6)}-${numbers.slice(6)}`;
    } else {
        return `(${numbers.slice(0, 2)}) ${numbers.slice(2, 7)}-${numbers.slice(7, 11)}`;
    }
}

function handlePhoneInput(value: string | number) {
    const numbers = String(value).replace(/\D/g, '');
    const limitedNumbers = numbers.slice(0, 11);
    phoneDisplay.value = formatPhone(limitedNumbers);
    const hiddenInput = document.querySelector('input[name="telefone"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
}

onMounted(() => {
    if (props.parent?.telefone) {
        phoneDisplay.value = formatPhone(props.parent.telefone);
    }
    if (props.parent?.cpf) {
        cpfDisplay.value = formatCPF(props.parent.cpf);
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="grid gap-2">
                <Label for="nome_completo">Nome completo</Label>
                <Input
                    id="nome_completo"
                    name="nome_completo"
                    :default-value="parent?.nome_completo ?? ''"
                    placeholder="Ex: Maria Silva"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.nome_completo" />
            </div>

            <div class="grid gap-2">
                <Label for="cpf">CPF</Label>
                <div class="relative">
                    <Input
                        id="cpf"
                        :model-value="cpfDisplay"
                        placeholder="000.000.000-00"
                        autocomplete="off"
                        :disabled="editMode"
                        maxlength="14"
                        :class="{
                            'cursor-not-allowed opacity-60': editMode,
                            'border-destructive focus-visible:ring-destructive':
                                cpfError || (cpfExists && !editMode),
                            'border-green-500 focus-visible:ring-green-500':
                                cpfValid &&
                                !cpfExists &&
                                !cpfError &&
                                !editMode &&
                                cpfDisplay.replace(/\D/g, '').length === 11,
                        }"
                        @update:model-value="handleCPFInput"
                        @keydown="
                            (e: KeyboardEvent) => {
                                const currentNumbers = cpfDisplay.replace(/\D/g, '');
                                const allowedKeys = [
                                    'Backspace',
                                    'Delete',
                                    'Tab',
                                    'ArrowLeft',
                                    'ArrowRight',
                                    'Home',
                                    'End',
                                    'Enter',
                                ];
                                if (e.ctrlKey || e.metaKey || allowedKeys.includes(e.key)) {
                                    return;
                                }
                                if (currentNumbers.length >= 11 && /[0-9]/.test(e.key)) {
                                    e.preventDefault();
                                }
                            }
                        "
                    />
                    <input type="hidden" name="cpf" :value="cpfDisplay.replace(/\D/g, '')" />
                    <div
                        v-if="cpfValidating"
                        class="absolute top-1/2 right-3 -translate-y-1/2"
                    >
                        <div
                            class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"
                        ></div>
                    </div>
                </div>
                <InputError :message="cpfError || errors.cpf" />
                <p
                    v-if="
                        cpfValid &&
                        !cpfExists &&
                        !cpfError &&
                        !editMode &&
                        cpfDisplay.replace(/\D/g, '').length === 11
                    "
                    class="text-xs text-green-600 dark:text-green-400"
                >
                    CPF válido e disponível
                </p>
                <p v-if="editMode" class="text-xs text-muted-foreground">
                    O CPF não pode ser alterado após o cadastro.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="data_nascimento">Data de nascimento</Label>
                <Input
                    id="data_nascimento"
                    name="data_nascimento"
                    type="date"
                    v-model="dataNascimento"
                />
                <InputError :message="errors.data_nascimento" />
            </div>

            <div class="grid gap-2">
                <Label for="parentesco">Parentesco</Label>
                <select
                    id="parentesco"
                    name="parentesco"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Selecione o parentesco</option>
                    <option
                        v-for="option in parentescoOptions"
                        :key="option"
                        :value="option"
                        :selected="parent?.parentesco === option"
                    >
                        {{ option }}
                    </option>
                    <option
                        v-if="parent?.parentesco && !parentescoOptions.includes(parent.parentesco)"
                        :value="parent.parentesco"
                        selected
                    >
                        {{ parent.parentesco }}
                    </option>
                </select>
                <InputError :message="errors.parentesco" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="grid gap-2">
                <Label for="profissao">Profissão</Label>
                <Input
                    id="profissao"
                    name="profissao"
                    :default-value="parent?.profissao ?? ''"
                    placeholder="Ex: Engenheiro, Professor, etc."
                />
                <InputError :message="errors.profissao" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <div class="relative">
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :model-value="emailDisplay"
                        placeholder="maria@exemplo.com"
                        autocomplete="email"
                        :class="{
                            'border-destructive focus-visible:ring-destructive':
                                emailError || emailExists,
                            'border-green-500 focus-visible:ring-green-500':
                                !emailError &&
                                !emailExists &&
                                !emailValidating &&
                                emailDisplay.length > 0 &&
                                isValidEmailFormat(emailDisplay),
                        }"
                        @update:model-value="handleEmailInput"
                        @blur="
                            () => {
                                if (emailDisplay && isValidEmailFormat(emailDisplay)) {
                                    checkEmailWithFetch(emailDisplay);
                                }
                            }
                        "
                    />
                    <div
                        v-if="emailValidating"
                        class="absolute top-1/2 right-3 -translate-y-1/2"
                    >
                        <div
                            class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"
                        ></div>
                    </div>
                </div>
                <InputError :message="emailError || errors.email" />
                <p
                    v-if="
                        !emailError &&
                        !emailExists &&
                        !emailValidating &&
                        emailDisplay.length > 0 &&
                        isValidEmailFormat(emailDisplay)
                    "
                    class="text-xs text-green-600 dark:text-green-400"
                >
                    E-mail disponível
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="telefone">Telefone</Label>
                <div class="relative">
                    <Input
                        id="telefone"
                        :model-value="phoneDisplay"
                        placeholder="(11) 99999-9999 ou (11) 3333-4444"
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

            <div class="grid gap-2">
                <Label for="ativo">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <input
                        type="hidden"
                        name="ativo"
                        :value="parent?.ativo === false ? '0' : '1'"
                    />
                    <input
                        id="ativo"
                        type="checkbox"
                        name="_ativo_toggle"
                        class="h-4 w-4 rounded border border-input"
                        :checked="parent?.ativo !== false"
                        @change="
                            (e) => {
                                const checked = (e.target as HTMLInputElement).checked;
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
                    <span class="text-muted-foreground">
                        {{ parent?.ativo === false ? 'Inativo' : 'Ativo' }}
                    </span>
                </label>
                <InputError :message="errors.ativo" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="observacoes">Observações</Label>
            <textarea
                id="observacoes"
                name="observacoes"
                rows="3"
                v-model="observacoes"
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Observações adicionais sobre o responsável..."
            />
            <InputError :message="errors.observacoes" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button
                type="submit"
                :disabled="processing || cpfExists || cpfValidating || emailExists || emailValidating"
                class="flex items-center gap-2"
            >
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
