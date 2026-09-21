<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Smartphone } from 'lucide-vue-next';
</script>

<template>
    <AuthLayout
        title="Senha alterada!"
        description="Tudo certo com a sua nova senha"
    >
        <Head title="Senha alterada com sucesso" />

        <div class="flex flex-col items-center gap-6 text-center">
            <div
                class="flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-9" />
            </div>

            <div class="space-y-3">
                <p class="text-base font-medium text-foreground">
                    Sua senha foi redefinida com sucesso.
                </p>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Abra o aplicativo
                    <strong class="font-semibold text-foreground">Edully</strong>
                    no seu celular e entre com a nova senha.
                </p>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Não é necessário acessar o sistema pela web — o acesso dos
                    responsáveis é feito pelo app.
                </p>
            </div>

            <div
                class="flex w-full items-start gap-3 rounded-xl border border-border bg-muted/40 p-4 text-left"
            >
                <Smartphone class="mt-0.5 size-5 shrink-0 text-primary" />
                <div class="space-y-1">
                    <p class="text-sm font-medium text-foreground">
                        Próximo passo
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Abra o app Edully → tela de login → digite seu CPF e a
                        nova senha.
                    </p>
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                Você já pode fechar esta página.
            </p>
        </div>
    </AuthLayout>
</template>
