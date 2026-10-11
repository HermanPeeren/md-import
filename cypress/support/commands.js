/**
 * Logging in, and the assertions every spec needs.
 */

/**
 * Sign in to the administrator, once per spec run. The credentials come from
 * `cypress.env.json`, which is git-ignored, and are never written into a spec.
 */
Cypress.Commands.add('loginToAdmin', () => {
  const user = Cypress.env('adminUser');
  const password = Cypress.env('adminPassword');

  cy.session(
    ['joomla-admin', user],
    () => {
      cy.visit('/administrator/index.php');

      cy.get('#mod-login-username').type(user);
      cy.get('#mod-login-password').type(password, { log: false });
      cy.get('#btn-login-submit').click();

      cy.get('#sidebarmenu, .header', { timeout: 20000 }).should('exist');
    },
    {
      validate() {
        cy.request('/administrator/index.php').its('status').should('eq', 200);
      },
    },
  );
});

/**
 * The page rendered something, and Joomla is not showing an error instead.
 */
Cypress.Commands.add('shouldHaveRendered', () => {
  cy.get('body').invoke('text').should((text) => {
    expect(text.trim().length, 'the page has content').to.be.greaterThan(200);
  });

  cy.get('body').should('not.contain', 'Fatal error');
  cy.get('body').should('not.contain', 'Warning:');
  cy.get('.alert-danger').should('not.exist');
});

/**
 * No language constant shows as itself. Joomla prints an undefined key in
 * capitals and logs nothing, so this reads what a person would read.
 */
Cypress.Commands.add('shouldShowNoRawConstants', () => {
  cy.get('body').invoke('prop', 'innerText').should((text) => {
    const raw = [...new Set(text.match(/\b(?:COM|PKG|LIB)_MDIMPORT[A-Z0-9_]*\b/g) || [])];

    expect(raw, 'constants shown untranslated').to.deep.equal([]);
  });
});
