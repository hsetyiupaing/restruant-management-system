<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <!--
    `mori-field` (resources/css/mori.css) supplies the surface, the 3:1 border
    and the focus ring. It replaces shadcn's `border-input bg-transparent` and
    `dark:bg-input/30`, which painted a grey slab over the page in dark mode.
  -->
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'mori-field file:text-foreground selection:bg-mori-wine selection:text-white h-9 px-3 py-1 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none',
      props.class,
    )"
  >
</template>
