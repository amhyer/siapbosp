import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Bind ke hostname, bukan ::1 — Vite yang memakai literal IPv6 menulis
        // public/hot jadi http://[::1]:5173, dan Chromium menolak literal IPv6
        // dalam CSP (sumber diabaikan → aset dev diblokir halaman).
        host: 'localhost',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
