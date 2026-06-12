import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import * as Pusher from 'pusher-js';
import Echo from 'laravel-echo';

window.Pusher = Pusher;

try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? 'pulsara-key',
        wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        forceTLS: false,
        enabledTransports: ['ws', 'wss'],
    });
} catch (e) {
    // Echo not available
}
