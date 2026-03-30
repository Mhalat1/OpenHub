

//Le CORS c'est une règle du navigateur qui dit :
//"Je n'autorise pas un site à appeler un autre site d'une adresse différente."

//Donc localhost:5173 ne peut pas appeler localhost:8000 directement — 
// ports différents = adresses différentes.
//Avec le proxy Vite, ton navigateur croit qu'il parle à localhost:5173 tout le temps. 
// C'est Vite qui va chercher la réponse sur localhost:8000 dans son coin, et la ramène au 
// navigateur. Le navigateur ne sait pas que 8000 existe.


import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',  
        changeOrigin: true,
        secure: false
      }
    }
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/setupTests.js',
  },
});