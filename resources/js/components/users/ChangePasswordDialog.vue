<script setup lang="ts">
import { changePassword } from '@/actions/App/Http/Controllers/UsersController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/vue3';
import { KeyRound, X, Check } from 'lucide-vue-next';
import type { User } from '@/types';

defineProps<{
    user: User;
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="hover:bg-transparent"
            >
                <KeyRound class="h-4 w-4 text-blue-500 dark:text-blue-400" />
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <Form
                v-bind="changePassword.form({ user: user.id })"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <DialogHeader class="space-y-2">
                    <DialogTitle class="leading-snug">
                        Alterar senha
                    </DialogTitle>
                    <DialogDescription class="leading-relaxed">
                        Altere a senha do usuário
                        <span class="font-medium text-foreground">{{
                            (user as any).nome_completo || user.name
                        }}</span
                        >.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="password">Nova senha</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            required
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirmar senha</Label
                        >
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="••••••••"
                            required
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary" class="flex items-center gap-2">
                            <X class="h-4 w-4" />
                            Cancelar
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="processing"
                        class="flex items-center gap-2"
                    >
                        <Check class="h-4 w-4" />
                        {{ recentlySuccessful ? 'Salvo!' : 'Salvar senha' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

