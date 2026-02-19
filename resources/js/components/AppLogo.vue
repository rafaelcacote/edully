<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);

const appName = computed(() => {
    const user = auth.value?.user;
    const currentTenant = auth.value?.current_tenant;

    // Se for administrador geral, mostrar "Eduly"
    if (user?.is_admin_geral) {
        return 'Eduly';
    }

    // Se tiver tenant vinculado, mostrar nome da escola
    if (currentTenant?.nome) {
        return currentTenant.nome;
    }

    // Fallback
    return 'Eduly';
});

const appLogo = computed(() => {
    const user = auth.value?.user;
    const currentTenant = auth.value?.current_tenant;

    // Se for administrador geral, usar logo do Eduly
    if (user?.is_admin_geral) {
        return '/images/logo.svg';
    }

    // Se tiver tenant vinculado e tiver logo, mostrar logo da escola
    if (currentTenant?.logo_url) {
        return currentTenant.logo_url;
    }

    // Caso contrário, retornar null para usar o ícone padrão
    return null;
});
</script>

<template>
    <div
        v-if="appLogo"
        class="flex h-10 items-center justify-center rounded-md overflow-hidden"
    >
        <img
            :src="appLogo"
            :alt="appName"
            class="h-full w-auto object-contain"
        />
    </div>
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
    </div>
  
</template>
