import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const host = (import.meta.env.VITE_REVERB_HOST || 'localhost').replace(/^(https?:\/\/|wss?:\/\/)/, '');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: host,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8083,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
