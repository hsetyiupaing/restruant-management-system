<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import AdminSidebar from '@/components/admin/AdminSidebar.vue';
</script>

<template>
    <div class="admin-shell flex min-h-screen">
        <AdminSidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <slot />
        </div>
        <Toaster position="bottom-right" />
    </div>
</template>
