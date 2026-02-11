// jest.setup.env.js
// This file runs before setupTests.js and sets environment variables
// that will replace import.meta.env.VITE_* values after Babel transformation

process.env.VITE_API_URL = 'http://localhost:8000';
process.env.MODE = 'test';
process.env.DEV = 'false';
process.env.PROD = 'false';
process.env.SSR = 'false';
process.env.BASE_URL = '/';