<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-[#7a1f1f]">
            {{ message }}
        </p>
    </div>
</template>
