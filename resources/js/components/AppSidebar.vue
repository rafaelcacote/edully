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
const isAdminGeral = computed(() => page.props.auth.user?.is_admin_geral ?? false);
const isAdminEscola = computed(() => {
    const user = page.props.auth.user;
    if (!user) return false;
    
    // Verifica se o usuário tem a role "Administrador Escola"
    const roles = (user as any).roles || [];
    const hasAdminEscolaRole = roles.includes('Administrador Escola');
    
    // Verifica se o usuário tem pelo menos um tenant associado
    const tenants = (user as any).tenants || [];
    const hasTenants = tenants.length > 0;
    
    // Retorna true apenas se tiver a role E tenants associados
    return hasAdminEscolaRole && hasTenants;
});

const isProfessor = computed(() => {
    const user = page.props.auth.user;
    if (!user) return false;
    
    // Verifica se o usuário tem a role "Professor"
    const roles = (user as any).roles || [];
    const hasProfessorRole = roles.includes('Professor');
    
    // Verifica se o usuário tem pelo menos um tenant associado
    const tenants = (user as any).tenants || [];
    const hasTenants = tenants.length > 0;
    
    // Retorna true apenas se tiver a role E tenants associados
    return hasProfessorRole && hasTenants;
});

const hasExerciciosPermission = computed(() => {
    const user = page.props.auth.user;
    if (!user) return false;
    
    // Verifica se tem permissão de visualizar exercícios
    // As permissões podem estar em user.permissions ou precisamos verificar via role
    const roles = (user as any).roles || [];
    
    // Se for Administrador Escola ou Professor, tem acesso
    return roles.includes('Administrador Escola') || roles.includes('Professor');
});

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
    
    // Se for Administrador Escola, mostra todos os itens
    if (isAdminEscola.value) {
        items.push(
            {
                title: 'Perfil da Escola',
                href: '/school/profile',
                icon: School,
            },
            {
                title: 'Alunos',
                href: '/school/students',
                icon: GraduationCap,
            },
            {
                title: 'Responsáveis',
                href: '/school/parents',
                icon: Users,
            },
            {
                title: 'Professores',
                href: '/school/teachers',
                icon: UserCheck,
            },
            {
                title: 'Turmas',
                href: '/school/classes',
                icon: BookOpen,
            },
            {
                title: 'Disciplinas',
                href: '/school/disciplinas',
                icon: BookText,
            },
            {
                title: 'Exercícios',
                href: '/school/exercises',
                icon: NotebookPen,
            },
            {
                title: 'Provas',
                href: '/school/tests',
                icon: ClipboardCheck,
            }
        );
    } 
    // Se for Professor, mostra Disciplinas, Exercícios e Provas
    else if (isProfessor.value || hasExerciciosPermission.value) {
        items.push(
            {
                title: 'Disciplinas',
                href: '/school/disciplinas',
                icon: BookText,
            },
            {
                title: 'Exercícios',
                href: '/school/exercises',
                icon: NotebookPen,
            },
            {
                title: 'Provas',
                href: '/school/tests',
                icon: ClipboardCheck,
            }
        );
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
