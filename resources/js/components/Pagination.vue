<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex items-center justify-center gap-1">
        <Link
            v-for="(link, idx) in links"
            :key="idx"
            :href="link.url ?? ''"
            :aria-disabled="!link.url"
            class="rounded-sm px-3 py-1.5 text-sm transition"
            :class="
                link.active
                    ? 'bg-primary text-primary-foreground'
                    : link.url
                      ? 'hover:bg-neutral-100 dark:hover:bg-neutral-800'
                      : 'cursor-not-allowed opacity-50'
            "
            preserve-scroll
            preserve-state
        >
            <span v-html="link.label" />
        </Link>
    </nav>
</template>

