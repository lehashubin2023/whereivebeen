import { ref } from 'vue';
import { sessionEvent } from '@/actions/App/Http/Controllers/GameSession/GameSessionController';
import type { SessionEvent } from '@/types';

export function useSessionEvent() {
    const data = ref<SessionEvent | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    let controller: AbortController | null = null;

    function reset(): void {
        controller?.abort();
        controller = null;
        data.value = null;
        error.value = null;
        loading.value = false;
    }

    async function load(
        gameSessionId: number,
        sequence: number,
    ): Promise<void> {
        controller?.abort();

        const current = new AbortController();
        controller = current;

        data.value = null;
        error.value = null;
        loading.value = true;

        try {
            const response = await fetch(
                sessionEvent.url({ gameSession: gameSessionId, sequence }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: current.signal,
                },
            );

            if (!response.ok) {
                throw new Error(
                    `Request failed with status ${response.status}`,
                );
            }

            const payload = (await response.json()) as SessionEvent;

            if (controller === current) {
                data.value = payload;
            }
        } catch {
            if (controller === current) {
                error.value = 'Could not load this event.';
            }
        } finally {
            if (controller === current) {
                loading.value = false;
            }
        }
    }

    return { data, loading, error, load, reset };
}
