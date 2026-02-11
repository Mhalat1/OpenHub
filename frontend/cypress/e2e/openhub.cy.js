const { defineConfig } = require('cypress')

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:3000', // Votre URL
    setupNodeEvents(on, config) {
      // implement node event listeners here
    },
  },
})