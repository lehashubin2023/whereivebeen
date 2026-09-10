<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChartColumn,
    Download,
    Inbox,
    LifeBuoy,
    Map,
    ScrollText,
    User,
} from '@lucide/vue';
import { computed } from 'vue';
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

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
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
    {
        title: 'Report a problem',
        href: '/issue-report',
        icon: LifeBuoy,
    },
    ...(page.props.auth.user?.is_admin
        ? [
              {
                  title: 'Problem reports',
                  href: '/admin/issue-reports',
                  icon: Inbox,
              },
          ]
        : []),
]);
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
