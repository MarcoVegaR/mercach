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
        // Bind explicitly to localhost and a fixed port so Cloudflare Tunnel can target it
        host: '127.0.0.1',
        port: 5176,
        strictPort: true,
        cors: true,
        // Ensure tags injected by laravel-vite-plugin point to the Tunnel origin
        origin: 'https://vite.caracoders.com.ve',
        // HMR over secure WebSocket via the same Tunnel host on port 443
        hmr: {
            protocol: 'wss',
            host: 'vite.caracoders.com.ve',
            clientPort: 443,
        },
    },
});
