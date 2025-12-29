<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ToastType = 'success' | 'error' | 'info';

type ToastPayload = {
    type: ToastType;
    message: string;
    title?: string;
};

type ToastItem = ToastPayload & {
    id: string;
};

const page = usePage();

const incomingToast = computed(() => {
    const props = page.props as unknown as { toast?: ToastPayload | null };
    return props.toast ?? null;
});

const toasts = ref<ToastItem[]>([]);
let lastToastSignature: string | null = null;

const stylesByType: Record<ToastType, { container: string; badge: string }> = {
    success: {
        container:
            'border-emerald-200 bg-emerald-50 text-emerald-950 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-50',
        badge:
            'bg-emerald-600 text-white dark:bg-emerald-500 dark:text-emerald-950',
    },
    error: {
        container:
            'border-red-200 bg-red-50 text-red-950 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-50',
        badge: 'bg-red-600 text-white dark:bg-red-500 dark:text-red-950',
    },
    info: {
        container:
            'border-neutral-200 bg-white text-neutral-950 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-50',
        badge:
            'bg-neutral-900 text-white dark:bg-neutral-50 dark:text-neutral-900',
    },
};

function dismiss(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    incomingToast,
    (toast) => {
        if (!toast) return;

        const signature = `${toast.type}:${toast.title ?? ''}:${toast.message}`;
        if (signature === lastToastSignature) return;
        lastToastSignature = signature;

        const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        toasts.value = [
            ...toasts.value,
            {
                id,
                ...toast,
            },
        ];

        window.setTimeout(() => dismiss(id), 4500);
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="pointer-events-none fixed right-4 top-4 z-50 flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-2 sm:right-6 sm:top-6"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-for="t in toasts"
                :key="t.id"
                class="pointer-events-auto rounded-lg border shadow-sm backdrop-blur"
                :class="stylesByType[t.type].container"
            >
                <div class="flex items-start gap-3 p-4">
                    <div
                        class="mt-0.5 inline-flex h-6 items-center rounded-full px-2 text-xs font-medium"
                        :class="stylesByType[t.type].badge"
                    >
                        {{ t.type === 'success' ? 'Sucesso' : t.type === 'error' ? 'Erro' : 'Info' }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <p v-if="t.title" class="truncate text-sm font-semibold">
                            {{ t.title }}
                        </p>
                        <p class="text-sm leading-relaxed opacity-90">
                            {{ t.message }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-sm px-2 py-1 text-sm opacity-70 transition hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        @click="dismiss(t.id)"
                        aria-label="Fechar"
                    >
                        ×
                    </button>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>

