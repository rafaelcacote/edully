<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: 'Menu',
    },
);

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>

                <SidebarMenuBadge
                    v-if="item.pulse || item.badge"
                    class="pointer-events-none right-2 flex items-center gap-1 bg-transparent p-0"
                >
                    <span
                        v-if="item.pulse"
                        class="relative flex h-2.5 w-2.5"
                        aria-label="Há itens que precisam de atenção"
                    >
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"
                        />
                        <span
                            class="relative inline-flex h-2.5 w-2.5 rounded-full bg-amber-500"
                        />
                    </span>
                    <span
                        v-if="item.badge"
                        class="flex h-5 min-w-5 items-center justify-center rounded-md bg-amber-500 px-1 text-[10px] font-semibold text-white tabular-nums"
                    >
                        {{ item.badge }}
                    </span>
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
