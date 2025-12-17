<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"
import { Input } from '@/components/ui/input'

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <Input
    data-slot="sidebar-input"
    data-sidebar="input"
    :class="cn(
      'h-9 w-full bg-muted/60 shadow-sm focus-visible:bg-card',
      props.class,
    )"
  >
    <slot />
  </Input>
</template>
