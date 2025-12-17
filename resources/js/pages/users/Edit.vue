<script setup lang="ts">
import UsersController from '@/actions/App/Http/Controllers/UsersController';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as usersEdit, index } from '@/routes/users';
import type { BreadcrumbItem, User } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import UserForm from './Partials/UserForm.vue';

interface Props {
    user: User;
    roles: string[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Usuários',
        href: index().url,
    },
    {
        title: 'Editar',
        href: usersEdit({ user: props.user.id }).url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Editar usuário: ${props.user.full_name}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="props.user.full_name"
                        description="Atualize os dados do usuário"
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
                    v-bind="UsersController.update.form({ user: props.user.id })"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <UserForm
                        :roles="props.roles"
                        :user="props.user"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                        :show-password-fields="true"
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
                            {{ props.user.created_at }}
                        </div>
                        <div>
                            <span class="font-medium text-foreground"
                                >Último login:</span
                            >
                            {{ props.user.last_login_at ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

