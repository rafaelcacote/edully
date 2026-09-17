<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as permissionsIndex } from '@/routes/permissions';
import { index as plansIndex } from '@/routes/plans';
import { index as rolesIndex } from '@/routes/roles';
import { index as subscriptionsIndex } from '@/routes/subscriptions';
import { index as tenantsIndex } from '@/routes/tenants';
import { index as usersIndex } from '@/routes/users';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BookOpen,
    BookText,
    ClipboardCheck,
    ClipboardList,
    CreditCard,
    FileSearch,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    KeyRound,
    LayoutGrid,
    MessageSquare,
    NotebookPen,
    School,
    Shield,
    UserCheck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => user.value?.roles ?? []);
const permissions = computed(() => user.value?.permissions ?? []);
const tenants = computed(() => user.value?.tenants ?? []);

const hasPermission = (permission: string): boolean => permissions.value.includes(permission);

const isAdminGeral = computed(() => user.value?.is_admin_geral ?? false);
const hasTenant = computed(() => tenants.value.length > 0);

const isAdminEscola = computed(() => roles.value.includes('Administrador Escola') && hasTenant.value);
const isProfessor = computed(() => roles.value.includes('Professor') && hasTenant.value);

const canViewSchoolProfile = computed(() => hasPermission('escola.perfil.visualizar'));
const canViewStudents = computed(() => hasPermission('escola.alunos.visualizar'));
const canViewParents = computed(() => hasPermission('escola.responsaveis.visualizar'));
const canViewTeachers = computed(() => hasPermission('escola.professores.visualizar'));
const canViewClasses = computed(() => hasPermission('escola.turmas.visualizar'));
const canViewDisciplinas = computed(() => hasPermission('escola.disciplinas.visualizar'));
const canViewExercises = computed(() => hasPermission('escola.exercicios.visualizar'));
const canViewTests = computed(() => hasPermission('escola.provas.visualizar'));
const canViewMessages = computed(() => hasPermission('escola.mensagens.visualizar'));
const canViewAvisos = computed(() => hasPermission('escola.avisos.visualizar'));
const canViewNotas = computed(() => hasPermission('escola.notas.visualizar'));

const hasAnySchoolPermission = computed(
    () =>
        canViewSchoolProfile.value ||
        canViewStudents.value ||
        canViewParents.value ||
        canViewTeachers.value ||
        canViewClasses.value ||
        canViewDisciplinas.value ||
        canViewExercises.value ||
        canViewTests.value ||
        canViewMessages.value ||
        canViewAvisos.value ||
        canViewNotas.value
);

const canAccessSchoolMenu = computed(
    () => hasTenant.value && (isAdminEscola.value || isProfessor.value || hasAnySchoolPermission.value)
);

const generalNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (isAdminGeral.value) {
        items.push(
            {
                title: 'Escolas',
                href: tenantsIndex(),
                icon: School,
            },
            {
                title: 'Logs do Sistema',
                href: auditLogsIndex(),
                icon: FileSearch,
            }
        );
    }

    if (canAccessSchoolMenu.value && canViewSchoolProfile.value) {
        items.push({
            title: 'Perfil da Escola',
            href: '/school/profile',
            icon: School,
        });
    }

    return items;
});

const peopleNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (!canAccessSchoolMenu.value) {
        return items;
    }

    if (canViewStudents.value) {
        items.push({
            title: 'Alunos',
            href: '/school/students',
            icon: GraduationCap,
        });
    }

    if (canViewParents.value) {
        items.push({
            title: 'Responsáveis',
            href: '/school/parents',
            icon: Users,
        });
    }

    if (canViewTeachers.value) {
        items.push({
            title: 'Professores',
            href: '/school/teachers',
            icon: UserCheck,
        });
    }

    return items;
});

const academicNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (!canAccessSchoolMenu.value) {
        return items;
    }

    if (canViewClasses.value) {
        items.push({
            title: 'Turmas',
            href: '/school/classes',
            icon: BookOpen,
        });
    }

    if (canViewDisciplinas.value) {
        items.push({
            title: 'Disciplinas',
            href: '/school/disciplinas',
            icon: BookText,
        });
    }

    return items;
});

const assessmentsNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (!canAccessSchoolMenu.value) {
        return items;
    }

    if (canViewExercises.value) {
        items.push({
            title: 'Exercícios',
            href: '/school/exercises',
            icon: NotebookPen,
        });
    }

    if (canViewTests.value) {
        items.push({
            title: 'Provas',
            href: '/school/tests',
            icon: ClipboardCheck,
        });
    }

    if (canViewNotas.value) {
        items.push({
            title: 'Notas',
            href: '/school/notas',
            icon: ClipboardList,
        });
        items.push({
            title: 'Boletins',
            href: '/school/boletins',
            icon: FileSpreadsheet,
        });
    }

    return items;
});

const communicationNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (!canAccessSchoolMenu.value) {
        return items;
    }

    if (canViewMessages.value) {
        items.push({
            title: 'Recados',
            href: '/school/messages',
            icon: MessageSquare,
        });
    }

    if (canViewAvisos.value) {
        items.push({
            title: 'Comunicados',
            href: '/school/avisos',
            icon: Bell,
        });
    }

    return items;
});

const plansAndSubscriptionsNavItems = computed<NavItem[]>(() => {
    if (!isAdminGeral.value) {
        return [];
    }

    return [
        {
            title: 'Planos',
            href: plansIndex(),
            icon: CreditCard,
        },
        {
            title: 'Assinaturas',
            href: subscriptionsIndex(),
            icon: FileText,
        },
    ];
});

const accessNavItems = computed<NavItem[]>(() => {
    if (!isAdminGeral.value) {
        return [];
    }

    return [
        {
            title: 'Usuários',
            href: usersIndex(),
            icon: Users,
        },
        {
            title: 'Roles',
            href: rolesIndex(),
            icon: Shield,
        },
        {
            title: 'Permissões',
            href: permissionsIndex(),
            icon: KeyRound,
        },
    ];
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="Geral" :items="generalNavItems" />
            <NavMain v-if="peopleNavItems.length > 0" label="Pessoas" :items="peopleNavItems" />
            <NavMain v-if="academicNavItems.length > 0" label="Acadêmico" :items="academicNavItems" />
            <NavMain v-if="assessmentsNavItems.length > 0" label="Avaliações" :items="assessmentsNavItems" />
            <NavMain v-if="communicationNavItems.length > 0" label="Comunicação" :items="communicationNavItems" />
            <NavMain
                v-if="plansAndSubscriptionsNavItems.length > 0"
                label="Planos e Assinaturas"
                :items="plansAndSubscriptionsNavItems"
            />
            <NavMain v-if="accessNavItems.length > 0" label="Acesso" :items="accessNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
