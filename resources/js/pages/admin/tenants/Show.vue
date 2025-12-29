<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, index } from '@/routes/tenants';
import type { BreadcrumbItem, Tenant } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, School } from 'lucide-vue-next';

function formatCEP(cep: string): string {
    if (!cep) return '';
    const numbers = cep.replace(/\D/g, '');
    if (numbers.length === 8) {
        return `${numbers.slice(0, 5)}-${numbers.slice(5, 8)}`;
    }
    return cep;
}

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
        title: props.tenant.name,
        href: `/admin/tenants/${props.tenant.id}`,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Detalhes: ${props.tenant.name}`" />

        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div class="mt-2">
                    <div class="mb-8 space-y-0.5">
                        <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                            <School class="h-5 w-5" />
                            {{ props.tenant.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Detalhes da escola
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
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
                    <Button as-child>
                        <Link :href="edit({ tenant: props.tenant.id })" class="flex items-center gap-2">
                            <Edit class="h-4 w-4" />
                            Editar
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="space-y-6">
                    <div>
                        <h3 class="mb-4 text-lg font-semibold">Informações Básicas</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Nome</p>
                                <p class="mt-1">{{ props.tenant.name }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">E-mail</p>
                                <p class="mt-1">{{ props.tenant.email }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Subdomínio</p>
                                <p class="mt-1">{{ props.tenant.subdomain ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">CNPJ</p>
                                <p class="mt-1">{{ props.tenant.cnpj ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Telefone</p>
                                <p class="mt-1">{{ props.tenant.phone ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Status</p>
                                <div class="mt-1">
                                    <Badge
                                        :variant="props.tenant.is_active ? 'default' : 'secondary'"
                                    >
                                        {{ props.tenant.is_active ? 'Ativo' : 'Inativo' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.tenant.address || props.tenant.endereco_numero || props.tenant.endereco_bairro || props.tenant.endereco_cidade">
                        <h3 class="mb-4 text-lg font-semibold">Endereço</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-if="props.tenant.address">
                                <p class="text-sm font-medium text-muted-foreground">Logradouro</p>
                                <p class="mt-1">{{ props.tenant.address }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_numero">
                                <p class="text-sm font-medium text-muted-foreground">Número</p>
                                <p class="mt-1">{{ props.tenant.endereco_numero }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_complemento">
                                <p class="text-sm font-medium text-muted-foreground">Complemento</p>
                                <p class="mt-1">{{ props.tenant.endereco_complemento }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_bairro">
                                <p class="text-sm font-medium text-muted-foreground">Bairro</p>
                                <p class="mt-1">{{ props.tenant.endereco_bairro }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_cep">
                                <p class="text-sm font-medium text-muted-foreground">CEP</p>
                                <p class="mt-1">{{ formatCEP(props.tenant.endereco_cep) }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_cidade">
                                <p class="text-sm font-medium text-muted-foreground">Cidade</p>
                                <p class="mt-1">{{ props.tenant.endereco_cidade }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_estado">
                                <p class="text-sm font-medium text-muted-foreground">Estado</p>
                                <p class="mt-1">{{ props.tenant.endereco_estado }}</p>
                            </div>
                            <div v-if="props.tenant.endereco_pais">
                                <p class="text-sm font-medium text-muted-foreground">País</p>
                                <p class="mt-1">{{ props.tenant.endereco_pais }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.tenant.logo_url">
                        <h3 class="mb-4 text-lg font-semibold">Logo</h3>
                        <img
                            :src="props.tenant.logo_url"
                            :alt="`Logo de ${props.tenant.name}`"
                            class="h-32 w-auto rounded-lg border"
                        />
                    </div>

                    <div v-if="props.tenant.trial_until">
                        <h3 class="mb-4 text-lg font-semibold">Trial</h3>
                        <p class="text-sm">
                            Válido até: {{ new Date(props.tenant.trial_until).toLocaleDateString('pt-BR') }}
                        </p>
                    </div>

                    <div class="border-t pt-6">
                        <h3 class="mb-4 text-lg font-semibold">Dados do Sistema</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Criado em</p>
                                <p class="mt-1 text-sm">
                                    {{ new Date(props.tenant.created_at).toLocaleString('pt-BR') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-muted-foreground">Atualizado em</p>
                                <p class="mt-1 text-sm">
                                    {{ new Date(props.tenant.updated_at).toLocaleString('pt-BR') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

