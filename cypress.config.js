import { defineConfig } from 'cypress';

/**
 * Cypress runs against a real Joomla with MD Import installed and seeded
 * (tools/seed-site.php), and the import it runs is the real one: it downloads
 * the help repository from GitHub. That is the point - nothing else here boots
 * Joomla's models, and nothing else proves the pages arrive.
 *
 * The site and the login come from `cypress.env.json`, which is git-ignored.
 * Copy `cypress.env.json.dist` and fill it in.
 */
export default defineConfig({
  e2e: {
    // Overridden by `baseUrl` in cypress.env.json.
    baseUrl: 'http://localhost/md-import/joomla',
    supportFile: 'cypress/support/e2e.js',
    specPattern: 'cypress/e2e/**/*.cy.js',
    video: false,
    screenshotOnRunFailure: true,
    // One run of the import downloads ~55 MB and saves ~280 articles twice.
    defaultCommandTimeout: 10000,

    setupNodeEvents(on, config) {
      if (config.env.baseUrl) {
        config.baseUrl = config.env.baseUrl;
      }

      if (!config.env.adminUser || !config.env.adminPassword) {
        throw new Error(
          'cypress.env.json needs adminUser and adminPassword. Copy cypress.env.json.dist and fill it in.',
        );
      }

      return config;
    },
  },
});
