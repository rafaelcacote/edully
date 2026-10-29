<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

defineProps<{
    label: string;
}>();
</script>

<template>
    <Tooltip>
        <TooltipTrigger as-child>
            <slot />
        </TooltipTrigger>
        <TooltipContent>
            <p>{{ label }}</p>
        </TooltipContent>
    </Tooltip>
</template>
