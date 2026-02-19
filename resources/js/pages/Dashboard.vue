<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as tenantsIndex } from '@/routes/tenants';
import { index as plansIndex } from '@/routes/plans';
import { index as subscriptionsIndex } from '@/routes/subscriptions';
import { index as usersIndex } from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { computed, ref } from 'vue';
import {
    ArrowRight,
    BookOpen,
    ClipboardCheck,
    CreditCard,
    FileText,
    GraduationCap,
    School,
    UserCheck,
    Users,
    Calendar,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';

interface AdminGeralStats {
    escolas: {
        total: number;
        ativas: number;
        inativas: number;
    };
    planos: {
        total: number;
        ativos: number;
        inativos: number;
    };
    assinaturas: {
        total: number;
        ativas: number;
        canceladas: number;
        pendentes: number;
    };
    usuarios: {
        total: number;
        ativos: number;
        inativos: number;
    };
}

interface AdminEscolaStats {
    professores: {
        total: number;
        ativos: number;
        inativos: number;
    };
    alunos: {
        total: number;
        ativos: number;
        inativos: number;
    };
    turmas: {
        total: number;
        ativas: number;
        inativas: number;
    };
    responsaveis: {
        total: number;
        ativos: number;
        inativos: number;
    };
}

interface ProfessorStats {
    total_provas: number;
    total_exercicios: number;
}

interface CalendarEvent {
    date: string;
    date_formatted: string;
    provas: Array<{
        id: string;
        titulo: string;
        data: string;
        data_formatted: string;
        horario?: string | null;
        turma?: string | null;
        disciplina?: string | null;
        tipo: string;
    }>;
    exercicios: Array<{
        id: string;
        titulo: string;
        data: string;
        data_formatted: string;
        turma?: string | null;
        disciplina?: string | null;
        tipo: string;
    }>;
    total: number;
}

interface Props {
    dashboardType: 'admin_geral' | 'admin_escola' | 'professor';
    stats: AdminGeralStats | AdminEscolaStats | ProfessorStats;
    tenant?: {
        id: string;
        nome: string;
        logo_url?: string | null;
    } | null;
    calendarEvents?: CalendarEvent[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage();
const auth = computed(() => page.props.auth as any);
const user = computed(() => auth.value?.user);

const userName = computed(() => {
    return user.value?.nome_completo || user.value?.name || 'Usuário';
});

const isAdminGeral = computed(() => user.value?.is_admin_geral ?? false);
const isAdminEscola = computed(() => !isAdminGeral.value && (user.value?.roles?.includes('Administrador Escola') ?? false));
const isProfessor = computed(() => !isAdminGeral.value && (user.value?.roles?.includes('Professor') ?? false));

const adminGeralStats = computed(() => (isAdminGeral.value ? (props.stats as AdminGeralStats) : null));
const adminEscolaStats = computed(() => (isAdminEscola.value ? (props.stats as AdminEscolaStats) : null));
const professorStats = computed(() => (isProfessor.value ? (props.stats as ProfessorStats) : null));
const calendarEvents = computed(() => props.calendarEvents || []);

// Calendário mensal
const currentDate = ref(new Date());
const currentMonth = computed(() => currentDate.value.getMonth());
const currentYear = computed(() => currentDate.value.getFullYear());

const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
const weekDays = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

const goToPreviousMonth = () => {
    const newDate = new Date(currentDate.value);
    newDate.setMonth(newDate.getMonth() - 1);
    currentDate.value = newDate;
};

const goToNextMonth = () => {
    const newDate = new Date(currentDate.value);
    newDate.setMonth(newDate.getMonth() + 1);
    currentDate.value = newDate;
};

const goToToday = () => {
    currentDate.value = new Date();
};

const getCalendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    
    // Primeiro dia do mês
    const firstDay = new Date(year, month, 1);
    const firstDayWeek = firstDay.getDay();
    
    // Último dia do mês
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    
    const days: Array<{
        date: Date;
        day: number;
        isCurrentMonth: boolean;
        isToday: boolean;
        events: CalendarEvent | null;
    }> = [];
    
    // Dias do mês anterior (para preencher o início da semana)
    const prevMonthLastDay = new Date(year, month, 0).getDate();
    for (let i = firstDayWeek - 1; i >= 0; i--) {
        const date = new Date(year, month - 1, prevMonthLastDay - i);
        days.push({
            date,
            day: prevMonthLastDay - i,
            isCurrentMonth: false,
            isToday: false,
            events: null,
        });
    }
    
    // Dias do mês atual
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        date.setHours(0, 0, 0, 0);
        
        const dateStr = date.toISOString().split('T')[0];
        const event = calendarEvents.value.find((e) => e.date === dateStr) || null;
        
        days.push({
            date,
            day,
            isCurrentMonth: true,
            isToday: date.getTime() === today.getTime(),
            events: event,
        });
    }
    
    // Dias do próximo mês (para completar a última semana)
    const remainingDays = 42 - days.length; // 6 semanas * 7 dias
    for (let day = 1; day <= remainingDays; day++) {
        const date = new Date(year, month + 1, day);
        days.push({
            date,
            day,
            isCurrentMonth: false,
            isToday: false,
            events: null,
        });
    }
    
    return days;
});

const selectedDay = ref<CalendarEvent | null>(null);
const selectDay = (day: { events: CalendarEvent | null }) => {
    selectedDay.value = day.events;
};
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
                            Olá, {{ userName }}!
                        </h1>
                        <p class="mt-1 text-white/80">
                            <template v-if="isAdminGeral">
                                Bem-vindo ao painel de administração geral.
                            </template>
                            <template v-else-if="isProfessor">
                                Bem-vindo ao painel do professor.
                            </template>
                            <template v-else>
                                Bem-vindo ao painel de administração da escola.
                            </template>
                        </p>

                        <div class="mt-4 flex items-center gap-2">
                            <span class="text-sm text-white/80">Perfil</span>
                            <Badge
                                variant="secondary"
                                class="border-white/20 bg-white/15 text-white"
                            >
                                <template v-if="isAdminGeral">Administrador Geral</template>
                                <template v-else-if="isProfessor">Professor</template>
                                <template v-else>Administrador Escola</template>
                            </Badge>
                            <template v-if="(isAdminEscola || isProfessor) && props.tenant">
                                <span class="text-sm text-white/80">•</span>
                                <span class="text-sm text-white/80">{{ props.tenant.nome }}</span>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dashboard Administrador Geral -->
            <template v-if="isAdminGeral && adminGeralStats">
                <!-- KPIs - Estatísticas Principais -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Card Escolas -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Escolas
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminGeralStats.escolas.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.escolas.ativas }} ativas
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.escolas.inativas }} inativas
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-brand-100"
                            >
                                <School class="size-6 text-brand-600" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link :href="tenantsIndex()">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todas
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Planos -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Planos
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminGeralStats.planos.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.planos.ativos }} ativos
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.planos.inativos }} inativos
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30"
                            >
                                <CreditCard class="size-6 text-green-600 dark:text-green-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link :href="plansIndex()">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Assinaturas -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Assinaturas
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminGeralStats.assinaturas.total }}
                                </p>
                                <div class="mt-3 flex flex-wrap gap-x-2 gap-y-1 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.assinaturas.ativas }} ativas
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.assinaturas.pendentes }} pendentes
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30"
                            >
                                <FileText class="size-6 text-purple-600 dark:text-purple-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link :href="subscriptionsIndex()">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todas
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Usuários -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Usuários
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminGeralStats.usuarios.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.usuarios.ativos }} ativos
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminGeralStats.usuarios.inativos }} inativos
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-orange-100 dark:bg-orange-900/30"
                            >
                                <Users class="size-6 text-orange-600 dark:text-orange-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link :href="usersIndex()">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>
                </div>

                <!-- Detalhes e Ações Rápidas -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Resumo Detalhado -->
                    <Card class="p-6 lg:col-span-2">
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold">
                                    Resumo do Sistema
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Visão geral das principais métricas
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <School class="size-4 text-brand-600" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Escolas Ativas
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminGeralStats.escolas.ativas }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminGeralStats.escolas.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <CreditCard class="size-4 text-green-600 dark:text-green-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Planos Ativos
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminGeralStats.planos.ativos }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminGeralStats.planos.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <FileText class="size-4 text-purple-600 dark:text-purple-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Assinaturas Ativas
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminGeralStats.assinaturas.ativas }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminGeralStats.assinaturas.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <Users class="size-4 text-orange-600 dark:text-orange-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Usuários Ativos
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminGeralStats.usuarios.ativos }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminGeralStats.usuarios.total }} total
                                </p>
                            </div>
                        </div>
                    </Card>

                    <!-- Ações Rápidas -->
                    <Card class="p-6">
                        <p class="text-lg font-semibold mb-4">Ações Rápidas</p>
                        <div class="grid gap-2">
                            <Link :href="tenantsIndex()">
                                <Button variant="outline" class="w-full justify-start">
                                    <School class="size-4 mr-2" />
                                    Gerenciar Escolas
                                </Button>
                            </Link>
                            <Link :href="plansIndex()">
                                <Button variant="outline" class="w-full justify-start">
                                    <CreditCard class="size-4 mr-2" />
                                    Gerenciar Planos
                                </Button>
                            </Link>
                            <Link :href="subscriptionsIndex()">
                                <Button variant="outline" class="w-full justify-start">
                                    <FileText class="size-4 mr-2" />
                                    Gerenciar Assinaturas
                                </Button>
                            </Link>
                            <Link :href="usersIndex()">
                                <Button variant="outline" class="w-full justify-start">
                                    <Users class="size-4 mr-2" />
                                    Gerenciar Usuários
                                </Button>
                            </Link>
                        </div>
                    </Card>
                </div>
            </template>

            <!-- Dashboard Administrador Escola -->
            <template v-else-if="isAdminEscola && adminEscolaStats">
                <!-- KPIs - Estatísticas Principais -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <!-- Card Professores -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Professores
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminEscolaStats.professores.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.professores.ativos }} ativos
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.professores.inativos }} inativos
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-brand-100"
                            >
                                <UserCheck class="size-6 text-brand-600" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/teachers">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Alunos -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Alunos
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminEscolaStats.alunos.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.alunos.ativos }} ativos
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.alunos.inativos }} inativos
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30"
                            >
                                <GraduationCap class="size-6 text-green-600 dark:text-green-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/students">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Turmas -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Turmas
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminEscolaStats.turmas.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.turmas.ativas }} ativas
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.turmas.inativas }} inativas
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30"
                            >
                                <BookOpen class="size-6 text-purple-600 dark:text-purple-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/classes">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todas
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Responsáveis -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Total de Responsáveis
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ adminEscolaStats.responsaveis.total }}
                                </p>
                                <div class="mt-3 flex gap-2 text-xs">
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.responsaveis.ativos }} ativos
                                    </span>
                                    <span class="text-muted-foreground">•</span>
                                    <span class="text-muted-foreground">
                                        {{ adminEscolaStats.responsaveis.inativos }} inativos
                                    </span>
                                </div>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-orange-100 dark:bg-orange-900/30"
                            >
                                <Users class="size-6 text-orange-600 dark:text-orange-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/parents">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>
                </div>

                <!-- Detalhes e Ações Rápidas -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <!-- Resumo Detalhado -->
                    <Card class="p-6 lg:col-span-2">
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold">
                                    Resumo da Escola
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Visão geral das principais métricas
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <UserCheck class="size-4 text-brand-600" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Professores Ativos
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminEscolaStats.professores.ativos }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminEscolaStats.professores.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <GraduationCap class="size-4 text-green-600 dark:text-green-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Alunos Ativos
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminEscolaStats.alunos.ativos }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminEscolaStats.alunos.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <BookOpen class="size-4 text-purple-600 dark:text-purple-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Turmas Ativas
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminEscolaStats.turmas.ativas }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminEscolaStats.turmas.total }} total
                                </p>
                            </div>

                            <div class="rounded-xl border border-border/70 p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <Users class="size-4 text-orange-600 dark:text-orange-400" />
                                    <p class="text-xs font-medium text-muted-foreground">
                                        Responsáveis Ativos
                                    </p>
                                </div>
                                <p class="text-2xl font-semibold">
                                    {{ adminEscolaStats.responsaveis.ativos }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    de {{ adminEscolaStats.responsaveis.total }} total
                                </p>
                            </div>
                        </div>
                    </Card>

                    <!-- Ações Rápidas -->
                    <Card class="p-6">
                        <p class="text-lg font-semibold mb-4">Ações Rápidas</p>
                        <div class="grid gap-2">
                            <Link href="/school/teachers">
                                <Button variant="outline" class="w-full justify-start">
                                    <UserCheck class="size-4 mr-2" />
                                    Gerenciar Professores
                                </Button>
                            </Link>
                            <Link href="/school/students">
                                <Button variant="outline" class="w-full justify-start">
                                    <GraduationCap class="size-4 mr-2" />
                                    Gerenciar Alunos
                                </Button>
                            </Link>
                            <Link href="/school/classes">
                                <Button variant="outline" class="w-full justify-start">
                                    <BookOpen class="size-4 mr-2" />
                                    Gerenciar Turmas
                                </Button>
                            </Link>
                            <Link href="/school/parents">
                                <Button variant="outline" class="w-full justify-start">
                                    <Users class="size-4 mr-2" />
                                    Gerenciar Responsáveis
                                </Button>
                            </Link>
                        </div>
                    </Card>
                </div>
            </template>

            <!-- Dashboard Professor -->
            <template v-else-if="isProfessor && professorStats">
                <!-- KPIs - Estatísticas Principais -->
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-2">
                    <!-- Card Provas -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Provas Agendadas
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ professorStats.total_provas }}
                                </p>
                                <p class="mt-3 text-xs text-muted-foreground">
                                    Próximos 60 dias
                                </p>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30"
                            >
                                <ClipboardCheck class="size-6 text-purple-600 dark:text-purple-400" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/tests">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todas
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>

                    <!-- Card Exercícios -->
                    <Card class="p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <p class="text-sm text-muted-foreground">
                                    Exercícios com Entrega
                                </p>
                                <p class="mt-2 text-3xl font-semibold">
                                    {{ professorStats.total_exercicios }}
                                </p>
                                <p class="mt-3 text-xs text-muted-foreground">
                                    Próximos 60 dias
                                </p>
                            </div>
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-brand-100"
                            >
                                <BookOpen class="size-6 text-brand-600" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <Link href="/school/exercises">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="w-full justify-between text-xs"
                                >
                                    Ver todos
                                    <ArrowRight class="size-3" />
                                </Button>
                            </Link>
                        </div>
                    </Card>
                </div>

                <!-- Calendário de Provas e Exercícios -->
                <Card class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="min-w-0">
                            <p class="text-lg font-semibold">
                                Calendário de Provas e Exercícios
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Clique em um dia para ver os detalhes
                            </p>
                        </div>
                    </div>

                    <!-- Navegação do Calendário -->
                    <div class="flex items-center justify-between mb-4">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="goToPreviousMonth"
                            class="h-8 w-8 p-0"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>
                        
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-semibold">
                                {{ monthNames[currentMonth] }} {{ currentYear }}
                            </h3>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="goToToday"
                                class="h-8 text-xs"
                            >
                                Hoje
                            </Button>
                        </div>
                        
                        <Button
                            variant="outline"
                            size="sm"
                            @click="goToNextMonth"
                            class="h-8 w-8 p-0"
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>

                    <!-- Grade do Calendário -->
                    <div class="grid grid-cols-7 gap-1 mb-4">
                        <!-- Cabeçalho dos dias da semana -->
                        <div
                            v-for="day in weekDays"
                            :key="day"
                            class="p-2 text-center text-xs font-medium text-muted-foreground"
                        >
                            {{ day }}
                        </div>

                        <!-- Dias do calendário -->
                        <button
                            v-for="(day, index) in getCalendarDays"
                            :key="index"
                            @click="selectDay(day)"
                            :class="[
                                'relative p-2 text-sm rounded-lg transition-colors',
                                'hover:bg-muted/50 focus:outline-none focus:ring-2 focus:ring-ring',
                                !day.isCurrentMonth && 'text-muted-foreground/40',
                                day.isToday && !day.events && 'bg-primary/10 text-primary font-semibold',
                                day.events && 'bg-primary/20 text-primary font-semibold hover:bg-primary/30',
                                !day.isToday && !day.events && day.isCurrentMonth && 'hover:bg-muted',
                            ]"
                        >
                            <span>{{ day.day }}</span>
                            
                            <!-- Indicadores de atividades -->
                            <div
                                v-if="day.events"
                                class="absolute bottom-1 left-1/2 transform -translate-x-1/2 flex gap-0.5"
                            >
                                <span
                                    v-if="day.events.provas.length > 0"
                                    class="w-1.5 h-1.5 rounded-full bg-purple-500"
                                    title="Prova"
                                />
                                <span
                                    v-if="day.events.exercicios.length > 0"
                                    class="w-1.5 h-1.5 rounded-full bg-brand-500"
                                    title="Exercício"
                                />
                            </div>
                        </button>
                    </div>

                    <!-- Legenda -->
                    <div class="flex items-center justify-center gap-6 text-xs text-muted-foreground mb-4">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-purple-500" />
                            <span>Prova</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-500" />
                            <span>Exercício</span>
                        </div>
                    </div>

                    <!-- Detalhes do dia selecionado -->
                    <div
                        v-if="selectedDay"
                        class="mt-6 rounded-xl border border-border/70 p-4 bg-muted/30"
                    >
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ selectedDay.date_formatted }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ selectedDay.total }}
                                    {{ selectedDay.total === 1 ? 'atividade' : 'atividades' }}
                                </p>
                            </div>
                            <Badge variant="outline" class="text-xs">
                                {{ new Date(selectedDay.date).toLocaleDateString('pt-BR', { weekday: 'long' }) }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <!-- Provas -->
                            <div
                                v-for="prova in selectedDay.provas"
                                :key="`prova-${prova.id}`"
                                class="flex items-start gap-3 rounded-lg bg-purple-50 dark:bg-purple-950/20 p-3"
                            >
                                <ClipboardCheck class="mt-0.5 size-4 text-purple-600 dark:text-purple-400 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ prova.titulo }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span v-if="prova.turma">
                                            Turma: {{ prova.turma }}
                                        </span>
                                        <span v-if="prova.disciplina">
                                            {{ prova.disciplina }}
                                        </span>
                                        <span v-if="prova.horario">
                                            Horário: {{ prova.horario }}
                                        </span>
                                    </div>
                                </div>
                                <Badge variant="secondary" class="bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 shrink-0">
                                    Prova
                                </Badge>
                            </div>

                            <!-- Exercícios -->
                            <div
                                v-for="exercicio in selectedDay.exercicios"
                                :key="`exercicio-${exercicio.id}`"
                                class="flex items-start gap-3 rounded-lg bg-brand-50 p-3"
                            >
                                <BookOpen class="mt-0.5 size-4 text-brand-600 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium">
                                        {{ exercicio.titulo }}
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span v-if="exercicio.turma">
                                            Turma: {{ exercicio.turma }}
                                        </span>
                                        <span v-if="exercicio.disciplina">
                                            {{ exercicio.disciplina }}
                                        </span>
                                    </div>
                                </div>
                                <Badge variant="secondary" class="bg-brand-100 text-brand-700 shrink-0">
                                    Exercício
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <!-- Mensagem quando não há eventos -->
                    <div
                        v-else-if="calendarEvents.length === 0"
                        class="py-12 text-center"
                    >
                        <Calendar class="mx-auto size-12 text-muted-foreground/50" />
                        <p class="mt-4 text-sm text-muted-foreground">
                            Nenhuma prova ou exercício agendado nos próximos 60 dias.
                        </p>
                    </div>

                    <!-- Mensagem quando não há seleção -->
                    <div
                        v-else
                        class="mt-6 py-8 text-center border border-dashed rounded-lg"
                    >
                        <Calendar class="mx-auto size-8 text-muted-foreground/50" />
                        <p class="mt-2 text-sm text-muted-foreground">
                            Clique em um dia com atividades para ver os detalhes
                        </p>
                    </div>
                </Card>
            </template>
        </div>
    </AppLayout>
</template>
