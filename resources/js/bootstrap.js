import axios from 'axios';

export const api = axios.create({
    baseURL: 'http://localhost:82/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export async function ensureCsrfCookie() {
    await axios.get('http://localhost:82/sanctum/csrf-cookie', { withCredentials: true }); 
}

window.axios = api;

