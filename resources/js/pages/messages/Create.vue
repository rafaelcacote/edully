<script setup lang="ts">
import { Button } from '@/components/ui/button';
import Heading from '@/components/Heading.vue';
import AppLayout from '@/layouts/AppLayout.vue';
function index() {
    return { url: '/messages' };
}
import type { BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from 'lucide-vue-next';
import MessageForm from './Partials/MessageForm.vue';

interface User {
    id: string;
    nome_completo: string;
    email: string;
}

interface Props {
    users: User[];
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Mensagens',
        href: index().url,
    },
    {
        title: 'Nova mensagem',
        href: '#',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Nova mensagem" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <Heading
                        title="Nova mensagem"
                        description="Envie uma nova mensagem"
                        :icon="Mail"
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
                    action="/messages"
                    method="post"
                    reset-on-success
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <MessageForm
                        :users="props.users"
                        submit-label="Enviar mensagem"
                        :processing="processing"
                        :errors="errors"
                    />
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
