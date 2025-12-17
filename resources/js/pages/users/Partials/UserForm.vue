<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';
import { Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    roles: string[];
    user?: User;
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
    showPasswordFields: boolean;
}>();

const phoneDisplay = ref('');
const cpfDisplay = ref('');

function formatCPF(value: string): string {
    // Remove tudo que não é número
    const numbers = value.replace(/\D/g, '');

    // Limita a 11 dígitos
    const limitedNumbers = numbers.slice(0, 11);

    // Aplica a máscara: XXX.XXX.XXX-XX
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
    // Remove caracteres não numéricos
    const numbers = value.replace(/\D/g, '');
    
    // Limita a 11 dígitos
    const limitedNumbers = numbers.slice(0, 11);
    
    // Aplica a máscara
    cpfDisplay.value = formatCPF(limitedNumbers);
    
    // Atualiza o campo hidden com o valor sem máscara
    const hiddenInput = document.querySelector('input[name="cpf"]') as HTMLInputElement;
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
    const hiddenInput = document.querySelector('input[name="phone"]') as HTMLInputElement;
    if (hiddenInput) {
        hiddenInput.value = limitedNumbers;
    }
}

onMounted(() => {
    if (props.user?.phone) {
        phoneDisplay.value = formatPhone(props.user.phone);
    }
    if (props.user?.cpf) {
        cpfDisplay.value = formatCPF(props.user.cpf);
    }
});
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="full_name">Nome completo</Label>
                <Input
                    id="full_name"
                    name="full_name"
                    :default-value="user?.full_name ?? ''"
                    placeholder="Ex: Maria Silva"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.full_name" />
            </div>

            <div class="grid gap-2">
                <Label for="cpf">CPF</Label>
                <div class="relative">
                    <Input
                        id="cpf"
                        :model-value="cpfDisplay"
                        placeholder="000.000.000-00"
                        autocomplete="off"
                        @update:model-value="handleCPFInput"
                    />
                    <input
                        type="hidden"
                        name="cpf"
                        :value="cpfDisplay.replace(/\D/g, '')"
                    />
                </div>
                <InputError :message="errors.cpf" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="email">E-mail</Label>
            <Input
                id="email"
                name="email"
                type="email"
                :default-value="user?.email ?? ''"
                placeholder="maria@exemplo.com"
                required
                autocomplete="username"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="role">Perfil</Label>
            <select
                id="role"
                name="role"
                class="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :default-value="user?.role ?? 'parent'"
                required
            >
                <option v-for="r in roles" :key="r" :value="r">
                    {{ r }}
                </option>
            </select>
            <InputError :message="errors.role" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="phone">Telefone</Label>
                <div class="relative">
                    <Input
                        id="phone"
                        :model-value="phoneDisplay"
                        placeholder="(11) 99999-9999 ou (11) 3333-4444"
                        autocomplete="tel"
                        @update:model-value="handlePhoneInput"
                    />
                    <input
                        type="hidden"
                        name="phone"
                        :value="phoneDisplay.replace(/\D/g, '')"
                    />
                </div>
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="is_active">Status</Label>
                <label
                    class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <input
                        type="hidden"
                        name="is_active"
                        :value="user?.is_active === false ? '0' : '1'"
                    />
                    <input
                        id="is_active"
                        type="checkbox"
                        name="_is_active_toggle"
                        class="h-4 w-4 rounded border border-input"
                        :checked="user?.is_active !== false"
                        @change="
                            (e) => {
                                const checked = (e.target as HTMLInputElement)
                                    .checked;
                                const hidden = (
                                    e.currentTarget as HTMLInputElement
                                )
                                    .closest('label')
                                    ?.querySelector(
                                        'input[type=hidden][name=is_active]',
                                    ) as HTMLInputElement | null;
                                if (hidden) hidden.value = checked ? '1' : '0';
                            }
                        "
                    />
                    <span class="text-muted-foreground"
                        >{{ user?.is_active === false ? 'Inativo' : 'Ativo' }}</span
                    >
                </label>
                <InputError :message="errors.is_active" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="avatar_url">Avatar (URL)</Label>
            <Input
                id="avatar_url"
                name="avatar_url"
                :default-value="user?.avatar_url ?? ''"
                placeholder="https://..."
                autocomplete="url"
            />
            <InputError :message="errors.avatar_url" />
        </div>

        <div v-if="showPasswordFields" class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="password"
                    >{{ user ? 'Nova senha' : 'Senha' }}</Label
                >
                <Input
                    id="password"
                    name="password"
                    type="password"
                    :required="!user"
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar senha</Label>
                <Input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    :required="!user"
                    autocomplete="new-password"
                    placeholder="••••••••"
                />
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

