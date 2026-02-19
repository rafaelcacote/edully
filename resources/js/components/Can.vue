<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    permission: string;
}

const props = defineProps<Props>();

const page = usePage();
const permissions = computed(() => (page.props.auth as any)?.user?.permissions ?? []);

const hasPermission = computed(() => permissions.value.includes(props.permission));
</script>

<template>
    <template v-if="hasPermission">
        <slot />
    </template>
</template>
