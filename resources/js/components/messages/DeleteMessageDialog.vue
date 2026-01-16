<script setup lang="ts">
import MessagesController from '@/actions/App/Http/Controllers/School/MessagesController';
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
import { Form } from '@inertiajs/vue3';
import { Check, Trash2, X } from 'lucide-vue-next';

defineProps<{
    message: {
        id: string;
        titulo: string;
    };
}>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="hover:bg-transparent">
                <Trash2 class="h-4 w-4 text-red-500 dark:text-red-400" />
            </Button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-lg">
            <Form
                v-bind="
                    MessagesController.destroy.form({
                        message: message.id,
                    })
                "
                :options="{ preserveScroll: true }"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-2">
                    <DialogTitle class="leading-snug">
                        Confirmar exclusão
                    </DialogTitle>
                    <DialogDescription class="leading-relaxed">
                        Você está prestes a excluir a mensagem
                        <span class="font-medium text-foreground">{{
                            message.titulo
                        }}</span
                        >. Essa ação não pode ser desfeita.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-lg border border-red-100 bg-red-50 p-4 text-sm text-red-900 dark:border-red-200/10 dark:bg-red-700/10 dark:text-red-100"
                >
                    <p class="font-medium">Atenção</p>
                    <p class="mt-1 opacity-90">
                        Recomendamos confirmar se esta mensagem não está em uso
                        em outras rotinas do sistema.
                    </p>
                    <InputError class="mt-2" :message="errors.message" />
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
                        variant="destructive"
                        :disabled="processing"
                        class="flex items-center gap-2"
                    >
                        <Check class="h-4 w-4" />
                        Sim, excluir
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
