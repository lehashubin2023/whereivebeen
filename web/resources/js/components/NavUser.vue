<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { logout } from '@/routes';

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <SidebarMenuButton as-child tooltip="Log out">
                <Link
                    :href="logout()"
                    as="button"
                    data-test="logout-button"
                    class="w-full cursor-pointer"
                    @click="handleLogout"
                >
                    <LogOut />
                    <span>Log out</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
