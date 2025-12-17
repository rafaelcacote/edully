<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { computed } from 'vue';
import {
    ArrowUpRight,
    BarChart3,
    Link2,
    Plus,
    Sparkles,
    TrendingUp,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const auth = computed(() => page.props.auth as any);

const creditsTotal = 1000;
const creditsUsed = 700;
const creditsRemaining = creditsTotal - creditsUsed;
const creditsUsagePct = Math.round((creditsUsed / creditsTotal) * 100);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Hero -->
            <section class="bg-brand-gradient rounded-2xl p-6 text-white shadow-sm">
                <div
                    class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between"
                >
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold md:text-3xl">
                            Olá, {{ auth?.user?.name ?? 'Sifet' }}!
                        </h1>
                        <p class="mt-1 text-white/80">
                            Vamos construir algo incrível hoje.
                        </p>

                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-sm text-white/80">Plano</span>
                            <Badge
                                variant="secondary"
                                class="border-white/20 bg-white/15 text-white"
                            >
                                Profissional
                            </Badge>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            class="border-white/30 bg-white/95 text-slate-900 hover:bg-white"
                        >
                            <Plus class="size-4" />
                            Novo Artigo
                        </Button>
                        <Button
                            variant="secondary"
                            class="border border-white/20 bg-white/15 text-white hover:bg-white/20"
                        >
                            <Link2 class="size-4" />
                            Integrações
                        </Button>
                        <Button
                            variant="secondary"
                            class="border border-white/20 bg-white/15 text-white hover:bg-white/20"
                        >
                            <TrendingUp class="size-4" />
                            Ver Planos
                        </Button>
                    </div>
                </div>
            </section>

            <!-- KPIs -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Créditos restantes
                            </p>
                            <p class="mt-2 text-3xl font-semibold">
                                {{ creditsRemaining }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ creditsUsed }}/{{ creditsTotal }} usados
                            </p>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-accent"
                        >
                            <Sparkles class="size-5 text-primary" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full rounded-full bg-muted">
                            <div
                                class="bg-brand-gradient h-2 rounded-full"
                                :style="{ width: `${creditsUsagePct}%` }"
                            />
                        </div>
                    </div>
                </Card>

                <Card class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Uso de créditos
                            </p>
                            <p class="mt-2 text-3xl font-semibold">
                                {{ creditsUsagePct }}%
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Este mês
                            </p>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-accent"
                        >
                            <BarChart3 class="size-5 text-primary" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="h-2 w-full rounded-full bg-muted">
                            <div
                                class="bg-brand-gradient h-2 rounded-full"
                                :style="{ width: `${creditsUsagePct}%` }"
                            />
                        </div>
                    </div>
                </Card>

                <Card class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Plano atual
                            </p>
                            <p class="mt-2 text-2xl font-semibold">
                                Profissional
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Gerencie sua assinatura
                            </p>
                        </div>
                        <div
                            class="flex size-10 items-center justify-center rounded-xl bg-accent"
                        >
                            <TrendingUp class="size-5 text-primary" />
                        </div>
                    </div>
                </Card>

                <Card class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Dica do dia
                            </p>
                            <p class="mt-2 text-2xl font-semibold">
                                Otimize títulos
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Use termos com alta intenção
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="mt-0.5"
                            aria-label="Abrir"
                        >
                            <ArrowUpRight class="size-5 opacity-70" />
                        </Button>
                    </div>
                </Card>
            </div>

            <!-- Conteúdo -->
            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="p-6 lg:col-span-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-lg font-semibold">
                                Desempenho no Google Search Console
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Dados do seu domínio
                            </p>
                        </div>
                        <Button variant="outline" class="shrink-0">
                            Ver detalhes
                        </Button>
                    </div>

                    <div
                        class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="rounded-xl border border-border/70 p-4">
                            <p class="text-xs text-muted-foreground">Cliques</p>
                            <p class="mt-2 text-2xl font-semibold">4</p>
                        </div>
                        <div class="rounded-xl border border-border/70 p-4">
                            <p class="text-xs text-muted-foreground">
                                Impressões
                            </p>
                            <p class="mt-2 text-2xl font-semibold">968</p>
                        </div>
                        <div class="rounded-xl border border-border/70 p-4">
                            <p class="text-xs text-muted-foreground">CTR</p>
                            <p class="mt-2 text-2xl font-semibold">0.41%</p>
                        </div>
                        <div class="rounded-xl border border-border/70 p-4">
                            <p class="text-xs text-muted-foreground">Posição</p>
                            <p class="mt-2 text-2xl font-semibold">54.0</p>
                        </div>
                    </div>
                </Card>

                <Card class="p-6">
                    <p class="text-lg font-semibold">Atalhos</p>
                    <div class="mt-4 grid gap-2">
                        <Button variant="outline" class="justify-start">
                            <Plus class="size-4" />
                            Novo conteúdo
                        </Button>
                        <Button variant="outline" class="justify-start">
                            <Link2 class="size-4" />
                            Integrações
                        </Button>
                        <Button variant="outline" class="justify-start">
                            <TrendingUp class="size-4" />
                            Assinatura
                        </Button>
                    </div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
