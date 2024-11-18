import axios from 'axios';
import Echo from 'laravel-echo';
window.axios = axios;
window.Pusher = require('pusher-js');


window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,  // Ganti dengan key Pusher-mu
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,  // Cluster Pusher-mu
    forceTLS: true
});

import './echo';
