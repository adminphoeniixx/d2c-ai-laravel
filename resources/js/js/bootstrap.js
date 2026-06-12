import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo (Reverb over Pusher protocol) — loaded only if the packages and the
 * required VITE env vars are present, so development works even before
 * Reverb is configured.
 */
try {
    if (import.meta.env.VITE_REVERB_APP_KEY) {
        const Echo   = (await import('laravel-echo')).default;
        const Pusher = (await import('pusher-js')).default;

        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key:    import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            wssPort:Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }
} catch {
    // Echo/Pusher not installed yet — realtime disabled until you install them.
}
