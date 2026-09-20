<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { t } from '@/lib/i18n';
import { faq } from '@/routes';

const COOKIE_NAME = 'cookie_consent';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

const dismissed = ref(document.cookie.includes(`${COOKIE_NAME}=1`));

function accept(): void {
    document.cookie = `${COOKIE_NAME}=1;path=/;max-age=${COOKIE_MAX_AGE};SameSite=Lax`;
    dismissed.value = true;
}
</script>

<template>
    <Transition
        appear
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-6 opacity-0"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-6 opacity-0"
    >
        <div
            v-if="!dismissed"
            class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center p-4"
        >
            <div
                role="region"
                :aria-label="t('Cookie notice')"
                class="wow-panel pointer-events-auto flex w-full max-w-3xl flex-col gap-4 p-5 shadow-2xl sm:flex-row sm:items-center"
            >
                <p
                    class="text-xs leading-relaxed text-muted-foreground sm:text-sm"
                >
                    {{
                        t(
                            'This site uses only the cookies it needs to work: they keep you signed in and remember your language and interface settings. No analytics, no advertising, no third parties.',
                        )
                    }}
                </p>

                <div class="flex shrink-0 items-center gap-2 sm:ml-auto">
                    <Link :href="faq()" class="wow-btn-ghost">
                        {{ t('Details') }}
                    </Link>
                    <button type="button" class="wow-btn" @click="accept">
                        {{ t('Got it') }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
