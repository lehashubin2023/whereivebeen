import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

export function useBackgroundMap(): void {
    const page = usePage();

    watchEffect(() => {
        const map = page.props.backgroundMap;

        if (!map || typeof document === 'undefined') {
            return;
        }

        document.documentElement.style.setProperty(
            '--page-bg-image',
            `url('${map}')`,
        );
    });
}
