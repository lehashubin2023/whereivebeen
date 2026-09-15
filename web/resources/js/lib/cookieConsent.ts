import { createApp } from 'vue';
import CookieConsent from '@/components/CookieConsent.vue';

export function initializeCookieConsent(): void {
    const container = document.createElement('div');

    document.body.appendChild(container);

    createApp(CookieConsent).mount(container);
}
