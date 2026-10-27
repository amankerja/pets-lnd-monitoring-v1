import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Laravel otomatis membaca cookie XSRF-TOKEN; axios mengirim header X-XSRF-TOKEN secara default.
window.axios.defaults.withCredentials = true;

