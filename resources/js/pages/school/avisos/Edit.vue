<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Bell } from 'lucide-vue-next';
import AvisoForm from './Partials/AvisoForm.vue';

interface Aviso {
    id: string;
    titulo: string;
    conteudo: string;
    prioridade: string;
    publico_alvo: string;
    anexo_url: string | null;
    publicado: boolean;
    publicado_em: string | null;
    expira_em: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    aviso: Aviso;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Avisos',
        href: '/school/avisos',
    },
    {
        title: 'Editar',
        href: `/school/avisos/${props.aviso.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar aviso: ${props.aviso.titulo}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.aviso.titulo"
                        description="Atualize os dados do aviso"
                        :icon="Bell"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/avisos" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/avisos/${props.aviso.id}`"
                    method="patch"
                    enctype="multipart/form-data"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <AvisoForm
                        :aviso="props.aviso"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>

                <div class="mt-6 border-t pt-6">
                    <p class="text-sm font-medium">Dados do sistema</p>
                    <div
                        class="mt-2 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2"
                    >
                        <div>
                            <span class="font-medium text-foreground">Criado em:</span>
                            {{ new Date(props.aviso.created_at).toLocaleString('pt-BR') }}
                        </div>
                        <div>
                            <span class="font-medium text-foreground">Atualizado em:</span>
                            {{ new Date(props.aviso.updated_at).toLocaleString('pt-BR') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
