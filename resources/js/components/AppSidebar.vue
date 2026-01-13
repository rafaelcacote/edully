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
import { BookOpen, CreditCard, FileSearch, FileText, GraduationCap, KeyRound, LayoutGrid, School, Shield, UserCheck, Users, NotebookPen, ClipboardCheck, BookText } from 'lucide-vue-next';
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

const canViewSchoolProfile = computed(() => isAdminEscola.value || hasPermission('escola.perfil.visualizar'));
const canViewStudents = computed(() => isAdminEscola.value || hasPermission('escola.alunos.visualizar'));
const canViewParents = computed(() => isAdminEscola.value || hasPermission('escola.responsaveis.visualizar'));
const canViewTeachers = computed(() => isAdminEscola.value || hasPermission('escola.professores.visualizar'));
const canViewClasses = computed(() => isAdminEscola.value || hasPermission('escola.turmas.visualizar'));
const canViewDisciplinas = computed(() => isAdminEscola.value || hasPermission('escola.disciplinas.visualizar'));
const canViewExercises = computed(() => isAdminEscola.value || hasPermission('escola.exercicios.visualizar'));
const canViewTests = computed(() => isAdminEscola.value || hasPermission('escola.provas.visualizar'));

const hasAnySchoolPermission = computed(
    () =>
        canViewSchoolProfile.value ||
        canViewStudents.value ||
        canViewParents.value ||
        canViewTeachers.value ||
        canViewClasses.value ||
        canViewDisciplinas.value ||
        canViewExercises.value ||
        canViewTests.value
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

const usersAndPermissionsNavItems = computed<NavItem[]>(() => {
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

const schoolNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (!hasTenant.value || (!isAdminEscola.value && !isProfessor.value && !hasAnySchoolPermission.value)) {
        return items;
    }

    if (canViewSchoolProfile.value) {
        items.push({
            title: 'Perfil da Escola',
            href: '/school/profile',
            icon: School,
        });
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

    return items;
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
            <NavMain
                v-if="schoolNavItems.length > 0"
                label="Escola"
                :items="schoolNavItems"
            />
            <NavMain
                v-if="plansAndSubscriptionsNavItems.length > 0"
                label="Planos e Assinaturas"
                :items="plansAndSubscriptionsNavItems"
            />
            <NavMain
                v-if="usersAndPermissionsNavItems.length > 0"
                label="Usuários e Permissões"
                :items="usersAndPermissionsNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
