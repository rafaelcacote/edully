<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Save } from 'lucide-vue-next';

interface User {
    id: string;
    nome_completo: string;
    email: string;
}

interface Message {
    id?: string;
    destinatario_id?: string;
    titulo?: string;
    conteudo?: string;
    ativo?: boolean;
}

const props = defineProps<{
    message?: Message;
    users: User[];
    submitLabel: string;
    processing: boolean;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="grid gap-6">
        <div class="grid gap-2">
            <Label for="destinatario_id">Destinatário</Label>
            <select
                id="destinatario_id"
                name="destinatario_id"
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <option value="">Selecione um destinatário (opcional)</option>
                <option
                    v-for="user in props.users"
                    :key="user.id"
                    :value="user.id"
                    :selected="props.message?.destinatario_id === user.id"
                >
                    {{ user.nome_completo }} ({{ user.email }})
                </option>
            </select>
            <InputError :message="errors.destinatario_id" />
        </div>

        <div class="grid gap-2">
            <Label for="titulo">Título</Label>
            <Input
                id="titulo"
                name="titulo"
                :default-value="props.message?.titulo ?? ''"
                placeholder="Ex: Reunião de pais"
                required
            />
            <InputError :message="errors.titulo" />
        </div>

        <div class="grid gap-2">
            <Label for="conteudo">Conteúdo</Label>
            <textarea
                id="conteudo"
                name="conteudo"
                :default-value="props.message?.conteudo ?? ''"
                placeholder="Digite o conteúdo da mensagem..."
                rows="6"
                required
                class="flex min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            ></textarea>
            <InputError :message="errors.conteudo" />
        </div>

        <div class="grid gap-2">
            <Label for="ativo">Status</Label>
            <label
                class="flex h-10 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm"
            >
                <input
                    type="hidden"
                    name="ativo"
                    :value="props.message?.ativo === false ? '0' : '1'"
                />
                <input
                    id="ativo"
                    type="checkbox"
                    name="_ativo_toggle"
                    class="h-4 w-4 rounded border border-input"
                    :checked="props.message?.ativo !== false"
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
                    {{ props.message?.ativo === false ? 'Inativa' : 'Ativa' }}
                </span>
            </label>
            <InputError :message="errors.ativo" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <Button type="submit" :disabled="processing" class="flex items-center gap-2">
                <Save class="h-4 w-4" />
                {{ submitLabel }}
            </Button>
        </div>
    </div>
</template>
