import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig({
  base: '/wp-content/themes/arena/assets/dist/',
  build: {
    manifest: true,
    outDir: 'assets/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        main: resolve(process.cwd(), 'assets/src/js/main.js'),
      },
    },
  },
});
