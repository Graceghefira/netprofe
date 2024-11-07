import './bootstrap';
import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');
import Pusher from 'pusher-js';
window.Pusher = Pusher;


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


    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: 'd77e3fde3d3e05b26dc6',
        cluster: 'ap1',
        encrypted: true
    });

    window.Echo.channel('gib')
        .listen('.gib', (e) => {
            console.log('Received message:', e.message);
        });
