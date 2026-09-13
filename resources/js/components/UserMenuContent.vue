<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Monitor, Moon, Palette, Settings, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

function setAppearance(value: string | number): void {
    if (value === 'light' || value === 'dark' || value === 'system') {
        updateAppearance(value);
    }
}

interface Props {
    user: User;
}

const handleLogout = () => {
    router.post(
        logout().url,
        {},
        {
            onFinish: () => {
                router.flushAll();
            },
        },
    );
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Configurações
            </Link>
        </DropdownMenuItem>
        <DropdownMenuSub>
            <DropdownMenuSubTrigger>
                <Palette class="mr-2 h-4 w-4" />
                Tema
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuRadioGroup
                    :model-value="appearance"
                    @update:model-value="setAppearance"
                >
                    <DropdownMenuRadioItem
                        value="light"
                        data-test="theme-option-light"
                    >
                        <Sun class="h-4 w-4" />
                        Claro
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem
                        value="dark"
                        data-test="theme-option-dark"
                    >
                        <Moon class="h-4 w-4" />
                        Escuro
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem
                        value="system"
                        data-test="theme-option-system"
                    >
                        <Monitor class="h-4 w-4" />
                        Sistema
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <button
            type="button"
            class="flex w-full items-center"
            @click="handleLogout"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Sair
        </button>
    </DropdownMenuItem>
</template>
