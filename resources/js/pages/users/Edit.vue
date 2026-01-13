<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/UsersController';
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit as usersEdit, index } from '@/routes/users';
import type { BreadcrumbItem, User } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from 'lucide-vue-next';
import UserForm from './Partials/UserForm.vue';

interface Props {
    user: User;
    roles: string[];
    tenants?: Array<{ id: string; name: string }>;
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
        <Head :title="`Editar usuário: ${(props.user as any).nome_completo || props.user.name}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        :title="(props.user as any).nome_completo || props.user.name"
                        description="Atualize os dados do usuário"
                        :icon="Users"
                    />
                </div>

                <Button
                    variant="ghost"
                    as-child
                    class="mt-4 rounded-lg border border-input bg-background shadow-sm transition-all hover:bg-accent hover:text-accent-foreground hover:shadow-md"
                >
                    <Link :href="index()" class="flex items-center gap-2 px-4 py-2">
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                </Button>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <Form
                    v-bind="update.form({ user: props.user.id })"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <UserForm
                        :roles="props.roles"
                        :tenants="props.tenants"
                        :user="props.user"
                        submit-label="Salvar alterações"
                        :processing="processing"
                        :errors="errors"
                        :show-password-fields="false"
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

