import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        // Local development server
        host: '127.0.0.1',
        port: 5176,
        strictPort: true,
        cors: true,

        // Cloudflare Tunnel config (disabled by default)
        // Uncomment the following lines if you want to expose Vite through a Tunnel.
        // This will make the injected Vite assets point to the tunnel origin
        // instead of the local dev server.
        // origin: 'https://vite.caracoders.com.ve',
        // hmr: {
        //     protocol: 'wss',
        //     host: 'vite.caracoders.com.ve',
        //     clientPort: 443,
        // },
    },
});
