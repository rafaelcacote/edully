<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type Appearance, useAppearance } from '@/composables/useAppearance';
import { Check, Monitor, Moon, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const options: {
    value: Appearance;
    label: string;
    Icon: typeof Sun;
}[] = [
    { value: 'light', label: 'Claro', Icon: Sun },
    { value: 'dark', label: 'Escuro', Icon: Moon },
    { value: 'system', label: 'Sistema', Icon: Monitor },
];
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative h-8 w-8"
                data-test="theme-toggle"
                aria-label="Alterar tema"
            >
                <Sun
                    class="size-4 scale-100 rotate-0 transition-all dark:scale-0 dark:-rotate-90"
                />
                <Moon
                    class="absolute size-4 scale-0 rotate-90 transition-all dark:scale-100 dark:rotate-0"
                />
                <span class="sr-only">Alterar tema</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-40">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                class="cursor-pointer"
                :data-test="`theme-option-${option.value}`"
                @click="updateAppearance(option.value)"
            >
                <component :is="option.Icon" class="h-4 w-4" />
                <span>{{ option.label }}</span>
                <Check
                    v-if="appearance === option.value"
                    class="ml-auto h-4 w-4"
                />
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
