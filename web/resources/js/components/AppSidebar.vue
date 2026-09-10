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
    Users,
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
import { t } from '@/lib/i18n';
import type { NavItem } from '@/types';

const page = usePage();

const adminNavItems: NavItem[] = [
    {
        title: t('Problem reports'),
        href: '/admin/issue-reports',
        icon: Inbox,
    },
    {
        title: t('Users'),
        href: '/admin/users',
        icon: Users,
    },
    {
        title: t('Profile'),
        href: '/profile',
        icon: User,
    },
];

const userNavItems: NavItem[] = [
    {
        title: t('Sessions'),
        href: '/game-session/sessions',
        icon: Map,
    },
    {
        title: t('Statistics'),
        href: '/statistics',
        icon: ChartColumn,
    },
    {
        title: t('Imports'),
        href: '/game-session/imports',
        icon: ScrollText,
    },
    {
        title: t('Addon'),
        href: '/addon',
        icon: Download,
    },
    {
        title: t('Profile'),
        href: '/profile',
        icon: User,
    },
    {
        title: t('Report a problem'),
        href: '/issue-report',
        icon: LifeBuoy,
    },
];

const isAdmin = computed<boolean>(
    () => page.props.auth.user?.is_admin === true,
);

const mainNavItems = computed<NavItem[]>(() =>
    isAdmin.value ? adminNavItems : userNavItems,
);

const homeHref = computed<string>(() =>
    isAdmin.value ? '/admin/issue-reports' : '/game-session/sessions',
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
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
