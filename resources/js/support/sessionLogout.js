import { router } from '@inertiajs/vue3';

/**
 * POST /logout with one CSRF/session resync retry (lab: stale XSRF after long SPA session).
 */
export function postLogout(fallbackPath = '/') {
    const send = (isRetry) => {
        router.post('/logout', {}, {
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                if (typeof fallbackPath === 'string' && fallbackPath !== '') {
                    window.location.assign(fallbackPath);
                }
            },
            onError: () => {
                if (isRetry) {
                    window.location.assign(fallbackPath || '/');

                    return;
                }

                fetch(`${window.location.origin}/dashboard`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html' },
                }).finally(() => send(true));
            },
        });
    };

    send(false);
}
