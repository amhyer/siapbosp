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
        // Ikat IPv4 secara eksplisit.
        //
        // Tanpa ini Vite mengikat `localhost`, dan di Windows `localhost` lebih
        // dulu diselesaikan ke ::1 sehingga `public/hot` berisi
        // `http://[::1]:5173`. Literal IPv6 ber-kurung BUKAN host-source yang
        // sah menurut grammar CSP: Chromium membuangnya saat mem-parse
        // ("contains an invalid source ... It will be ignored"), sehingga
        // script-src/style-src tidak pernah cocok dan seluruh aset dev server
        // diblokir — halaman tampil polos walau alamat itu "sudah diizinkan".
        //
        // Dengan host di bawah, `public/hot` berisi http://127.0.0.1:5173 yang
        // sah bagi CSP. (Sisi PHP tetap punya normalisasi di App\Support\Aset
        // untuk berkas `hot` lama.)
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
