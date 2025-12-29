<script setup lang="ts">
import TenantsController from '@/actions/App/Http/Controllers/TenantsController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/tenants';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, School } from 'lucide-vue-next';
import TenantForm from './Partials/TenantForm.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Escolas',
        href: '/admin/tenants',
    },
    {
        title: 'Nova escola',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Nova escola" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <School class="h-5 w-5" />
                            Nova escola
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Cadastre uma nova escola na plataforma
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
                    v-bind="TenantsController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <TenantForm
                        submit-label="Criar escola"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

