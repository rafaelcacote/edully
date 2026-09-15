<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from 'lucide-vue-next';
import ParentForm from './Partials/ParentForm.vue';

interface Parent {
    id: string;
    usuario_id?: string;
    nome_completo?: string | null;
    cpf?: string | null;
    email?: string | null;
    telefone?: string | null;
    parentesco?: string | null;
    profissao?: string | null;
    data_nascimento?: string | null;
    observacoes?: string | null;
    ativo: boolean;
}

interface Props {
    parent: Parent;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Responsáveis',
        href: '/school/parents',
    },
    {
        title: 'Editar',
        href: `/school/parents/${props.parent.id}/edit`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar responsável: ${props.parent.nome_completo || 'Sem nome'}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.parent.nome_completo || 'Sem nome'"
                        description="Atualize os dados do responsável"
                        :icon="Users"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link href="/school/parents" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    :action="`/school/parents/${props.parent.id}`"
                    method="patch"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <ParentForm
                        :parent="props.parent"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                        :edit-mode="true"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

