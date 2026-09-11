import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import ProfileLayout from '@/layouts/profile/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import type { Auth } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'Support':
                return (page.props as { auth?: Auth }).auth?.user
                    ? AppLayout
                    : null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('profile/'):
                return [AppLayout, ProfileLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#d4a94e',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
