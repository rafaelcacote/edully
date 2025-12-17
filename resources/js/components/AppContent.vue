<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    variant?: 'header' | 'sidebar';
    class?: string;
}

const props = defineProps<Props>();
const className = computed(() => props.class);
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        :class="cn('px-4 py-4 md:px-6 md:py-6', className)"
    >
        <slot />
    </SidebarInset>
    <main
        v-else
        :class="
            cn(
                'mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl px-4 py-6 md:px-6',
                className,
            )
        "
    >
        <slot />
    </main>
</template>
