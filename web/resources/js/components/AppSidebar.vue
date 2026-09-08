<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChartColumn, Download, Map, ScrollText, User } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Sessions',
        href: '/game-session/sessions',
        icon: Map,
    },
    {
        title: 'Statistics',
        href: '/statistics',
        icon: ChartColumn,
    },
    {
        title: 'Imports',
        href: '/game-session/imports',
        icon: ScrollText,
    },
    {
        title: 'Addon',
        href: '/addon',
        icon: Download,
    },
    {
        title: 'Profile',
        href: '/profile',
        icon: User,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/game-session/sessions">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
