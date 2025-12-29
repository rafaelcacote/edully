<script setup lang="ts">
import TenantsController from '@/actions/App/Http/Controllers/TenantsController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as tenantsEdit, index } from '@/routes/tenants';
import type { BreadcrumbItem, Tenant } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, School } from 'lucide-vue-next';
import TenantForm from './Partials/TenantForm.vue';

interface Props {
    tenant: Tenant;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Escolas',
        href: '/admin/tenants',
    },
    {
        title: 'Editar',
        href: tenantsEdit({ tenant: props.tenant.id }).url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar escola: ${props.tenant.name}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <School class="h-5 w-5" />
                            {{ props.tenant.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Atualize os dados da escola
                        </p>
                    </div>
                </div>

                <Button
                    variant="outline"
                    as-child
                    class="rounded-lg"
                >
                    <Link :href="index()" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    v-bind="TenantsController.update.form({ tenant: props.tenant.id })"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <TenantForm
                        :tenant="props.tenant"
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
                            <span class="font-medium text-foreground"
                                >Criado em:</span
                            >
                            {{ new Date(props.tenant.created_at).toLocaleString('pt-BR') }}
                        </div>
                        <div>
                            <span class="font-medium text-foreground"
                                >Atualizado em:</span
                            >
                            {{ new Date(props.tenant.updated_at).toLocaleString('pt-BR') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

