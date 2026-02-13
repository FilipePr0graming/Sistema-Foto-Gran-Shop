import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    lib: {
      entry: resolve(__dirname, 'assets/src/js/hostinger-surveys.js'),
      name: 'HostingerSurveys',
      formats: ['iife'],
      fileName: () => 'js/hostinger-surveys.min.js',
      cssFileName: 'css/style.min',
    },
    rollupOptions: {
      external: ['jquery'],
      output: {
        globals: {
          jquery: 'jQuery',
        },
        assetFileNames: 'css/style.min.[ext]',
      },
    },
  },
});

