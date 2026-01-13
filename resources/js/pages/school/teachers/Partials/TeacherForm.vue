<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

interface Disciplina {
    id: string;
    nome: string;
    sigla?: string | null;
}

interface Teacher {
    id?: string;
    matricula?: string;
    disciplinas?: string[] | null;
    especializacao?: string | null;
    ativo?: boolean;
    nome_completo?: string;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
}

interface Props {
    teacher?: Teacher;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
    disciplinas?: Disciplina[];
}

const props = withDefaults(defineProps<Props>(), {
    disciplinas: () => [],
});

const phoneDisplay = ref('');
const cpfDisplay = ref('');
const selectedDisciplinas = ref<string[]>(normalizeDisciplinaIds(props.teacher?.disciplinas));
const cpfError = ref<string | null>(null);
const cpfValidating = ref(false);
const cpfValid = ref<boolean | null>(null);
const cpfExists = ref(false);

function normalizeDisciplinaIds(value: unknown): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => {
            if (typeof item === 'object' && item !== null && 'id' in (item as Record<string, unknown>)) {
                return String((item as Record<string, unknown>).id);
            }

            return String(item);
        })
        .filter((id) => id.length > 0);
}

function validateCpf(cpf: string): boolean {
    const numbers = cpf.replace(/\D/g, '');
    
    if (numbers.length !== 11) {
        return false;
    }
    
    // Check for known invalid CPFs (all same digits)
    if (/^(\d)\1{10}$/.test(numbers)) {
        return false;
    }
    
    // Validate check digits
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
    // Limita estritamente a 11 dígitos
    const limitedNumbers = numbers.slice(0, 11);
    
    // Se já tem 11 dígitos e o usuário está tentando adicionar mais, não atualiza
    const currentNumbers = cpfDisplay.value.replace(/\D/g, '');
    if (currentNumbers.length >= 11 && numbers.length > currentNumbers.length) {
        return;
    }
    
    cpfDisplay.value = formatCPF(limitedNumbers);
    const hiddenInput = document.querySelector('input[name="cpf"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
    
    // Reset validation state
    cpfError.value = null;
    cpfValid.value = null;
    cpfExists.value = false;
    
    // Only validate if we have 11 digits and it's not in edit mode
    if (limitedNumbers.length === 11 && !props.teacher?.id) {
        cpfValidating.value = true;
        
        // Validate CPF format
        const isValid = validateCpf(limitedNumbers);
        cpfValid.value = isValid;
        
        if (!isValid) {
            cpfError.value = 'CPF inválido';
            cpfValidating.value = false;
            return;
        }
        
        // Check if CPF already exists
        checkCpfWithFetch(limitedNumbers);
    } else if (limitedNumbers.length > 0 && limitedNumbers.length < 11 && !props.teacher?.id) {
        // Clear error if user is still typing
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
        // Obtém o CSRF token das props do Inertia
        const csrfToken = (page.props as any).csrfToken || getCookie('XSRF-TOKEN');
        
        const response = await fetch('/school/teachers/check-cpf', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
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
            cpfError.value = 'Este CPF já está cadastrado';
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

function setDisciplinaSelection(disciplinaId: string | number, checked: boolean) {
    userInteracted.value = true;
    const id = String(disciplinaId);

    if (checked) {
        if (!selectedDisciplinas.value.includes(id)) {
            selectedDisciplinas.value = [...selectedDisciplinas.value, id];
        }
    } else {
        selectedDisciplinas.value = selectedDisciplinas.value.filter((selectedId) => selectedId !== id);
    }
}

function handleDisciplinaChecked(disciplinaId: string | number, checked: boolean | 'indeterminate') {
    setDisciplinaSelection(disciplinaId, checked === true);
}

function toggleDisciplina(disciplinaId: string | number) {
    setDisciplinaSelection(disciplinaId, !isDisciplinaSelected(disciplinaId));
}

function isDisciplinaSelected(disciplinaId: string | number): boolean {
    const id = String(disciplinaId);
    return selectedDisciplinas.value.includes(id);
}

// Flag to track if user has interacted with disciplinas
const userInteracted = ref(false);

onMounted(() => {
    if (props.teacher?.telefone) {
        phoneDisplay.value = formatPhone(props.teacher.telefone);
    }
    if (props.teacher?.cpf) {
        cpfDisplay.value = formatCPF(props.teacher.cpf);
    }
    const initialDisciplinas = normalizeDisciplinaIds(props.teacher?.disciplinas);
    if (initialDisciplinas.length > 0) {
        selectedDisciplinas.value = initialDisciplinas;
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="space-y-4">
            <h3 class="text-lg font-semibold">Dados Pessoais</h3>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="nome_completo">
                        Nome completo <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="nome_completo"
                        name="nome_completo"
                        :default-value="teacher?.nome_completo ?? ''"
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
                            :disabled="!!teacher?.id"
                            maxlength="14"
                            :class="{
                                'border-destructive focus-visible:ring-destructive': cpfError || (cpfExists && !teacher?.id),
                                'border-green-500 focus-visible:ring-green-500': cpfValid && !cpfExists && !cpfError && !teacher?.id && cpfDisplay.replace(/\D/g, '').length === 11,
                            }"
                            @update:model-value="handleCPFInput"
                            @keydown="(e: KeyboardEvent) => {
                                const currentNumbers = cpfDisplay.replace(/\D/g, '');
                                // Permite teclas de navegação, edição e atalhos
                                const allowedKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter'];
                                if (e.ctrlKey || e.metaKey || allowedKeys.includes(e.key)) {
                                    return;
                                }
                                // Se já tem 11 dígitos e é um número, bloqueia
                                if (currentNumbers.length >= 11 && /[0-9]/.test(e.key)) {
                                    e.preventDefault();
                                }
                            }"
                        />
                        <input
                            type="hidden"
                            name="cpf"
                            :value="cpfDisplay.replace(/\D/g, '')"
                        />
                        <div v-if="cpfValidating" class="absolute right-3 top-1/2 -translate-y-1/2">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                        </div>
                    </div>
                    <InputError :message="cpfError || errors.cpf" />
                    <p v-if="cpfValid && !cpfExists && !cpfError && !teacher?.id && cpfDisplay.replace(/\D/g, '').length === 11" class="text-xs text-green-600 dark:text-green-400">
                        CPF válido e disponível
                    </p>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="email">E-mail</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        :default-value="teacher?.email ?? ''"
                        placeholder="maria@exemplo.com"
                        autocomplete="email"
                    />
                    <InputError :message="errors.email" />
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

            <div class="grid gap-2" v-if="!teacher?.id">
                <Label for="password">
                    Senha (opcional)
                </Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Mínimo 6 caracteres"
                    autocomplete="new-password"
                />
                <InputError :message="errors.password" />
                <p class="text-xs text-muted-foreground">
                    Deixe em branco para usar o CPF como senha padrão
                </p>
            </div>
        </div>

        <div class="space-y-4 border-t pt-6">
            <h3 class="text-lg font-semibold">Dados Profissionais</h3>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="matricula">
                        Matrícula <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="matricula"
                        name="matricula"
                        :default-value="teacher?.matricula ?? ''"
                        placeholder="Ex: PROF2024001"
                        required
                    />
                    <InputError :message="errors.matricula" />
                </div>

                <div class="grid gap-2">
                    <Label for="especializacao">Especialização</Label>
                    <Input
                        id="especializacao"
                        name="especializacao"
                        :default-value="teacher?.especializacao ?? ''"
                        placeholder="Ex: Educação Especial"
                    />
                    <InputError :message="errors.especializacao" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="disciplinas">Disciplinas</Label>
                <div v-if="props.disciplinas.length > 0" class="space-y-2 rounded-lg border border-input p-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="disciplina in props.disciplinas"
                            :key="disciplina.id"
                            class="flex items-center gap-2 rounded-md p-2 hover:bg-accent cursor-pointer"
                            @click="toggleDisciplina(disciplina.id)"
                        >
                            <Checkbox
                                :key="`checkbox-${disciplina.id}`"
                                :model-value="isDisciplinaSelected(disciplina.id)"
                                :aria-label="`Selecionar disciplina ${disciplina.nome}`"
                                @update:model-value="handleDisciplinaChecked(disciplina.id, $event)"
                                @click.stop
                            />
                            <div class="flex flex-col">
                                <span class="text-sm font-medium">{{ disciplina.nome }}</span>
                                <span v-if="disciplina.sigla" class="text-xs text-muted-foreground">
                                    {{ disciplina.sigla }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <input
                        type="hidden"
                        name="disciplinas"
                        :value="JSON.stringify(selectedDisciplinas)"
                    />
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Nenhuma disciplina ativa cadastrada. Cadastre disciplinas primeiro.
                </p>
                <InputError :message="errors.disciplinas" />
                <InputError
                    v-if="errors['disciplinas.0']"
                    :message="errors['disciplinas.0']"
                />
            </div>

            <div class="grid gap-2">
                <Label for="ativo">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <input
                        type="hidden"
                        name="ativo"
                        :value="teacher?.ativo === false ? '0' : '1'"
                    />
                    <input
                        id="ativo"
                        type="checkbox"
                        name="_ativo_toggle"
                        class="h-4 w-4 rounded border border-input"
                        :checked="teacher?.ativo !== false"
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
                        {{ teacher?.ativo === false ? 'Inativo' : 'Ativo' }}
                    </span>
                </label>
                <InputError :message="errors.ativo" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t pt-6">
            <Button 
                type="submit" 
                :disabled="processing" 
                class="flex items-center gap-2"
            >
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>

