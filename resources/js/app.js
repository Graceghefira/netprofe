import './bootstrap';
import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');

Echo.channel('log-channel')
    .listen('log.updated', (event) => {
        console.log(event.logs); // Menampilkan log yang diterima dari server
    });

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: process.env.MIX_PUSHER_APP_KEY,
        cluster: process.env.MIX_PUSHER_APP_CLUSTER,
        forceTLS: true
    });
