/**
 * Shared setup for every spec.
 */

import './commands';

// An uncaught exception from the page under test must still fail the test;
// only the browser's own ResizeObserver noise is let through.
Cypress.on('uncaught:exception', (err) => !/ResizeObserver loop/.test(err.message));
