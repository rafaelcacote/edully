<script setup lang="ts">
import UsersController from '@/actions/App/Http/Controllers/UsersController';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import UserForm from './Partials/UserForm.vue';

interface Props {
    roles: string[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Usuários',
        href: index().url,
    },
    {
        title: 'Novo usuário',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Novo usuário" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Novo usuário"
                        description="Cadastre um novo usuário na plataforma"
                    />
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
                    v-bind="UsersController.store.form()"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <UserForm
                        :roles="props.roles"
                        submit-label="Criar usuário"
                        :processing="processing"
                        :errors="errors"
                        :show-password-fields="true"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>

