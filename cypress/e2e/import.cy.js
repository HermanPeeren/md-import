/**
 * The import, end to end, against the real help repository on GitHub.
 *
 * Expects a site seeded by tools/seed-site.php: a "Help" menu heading in the
 * Main Menu, set as the Parent Menu Item. Everything else is at its default -
 * images go to images/jdm-help, articles to a category called Help with a
 * subcategory per section, and SEF URLs are on, as on a fresh Joomla.
 */

const runImport = () => {
  cy.loginToAdmin();
  cy.visit('/administrator/index.php?option=com_mdimport');
  cy.get('#mdimport-start').should('not.be.disabled').click();

  // Downloading and converting the whole manual takes minutes, not seconds.
  cy.get('#mdimport[data-finished="1"], #mdimport[data-failed]', { timeout: 15 * 60 * 1000 })
    .should('exist');
  cy.get('#mdimport').should('not.have.attr', 'data-failed');
};

const articlesTitled = (title) => {
  cy.loginToAdmin();
  cy.visit(`/administrator/index.php?option=com_content&view=articles&filter[search]=${encodeURIComponent(title)}&filter[published]=*&list[limit]=100`);

  return cy.get('#articleList tbody tr');
};

describe('the import screen', () => {
  it('renders with every string translated and is ready to start', () => {
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_mdimport');
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();

    cy.get('.mdimport-parent-menu-item').should('contain', 'Help');
    cy.get('.mdimport-image-base').should('contain', 'images/jdm-help/');
    cy.get('.mdimport-problem').should('not.exist');
    cy.get('#mdimport-start').should('not.be.disabled');
  });

  it('shows its options with every string translated', () => {
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_config&view=component&component=com_mdimport');
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();
    cy.get('#jform_parent_menu_item').should('exist');
    cy.get('#jform_images_folder').should('exist');
  });
});

describe('importing', () => {
  it('imports the whole manual', () => {
    runImport();
    cy.get('#mdimport-log').should('contain', 'Pages converted');
    cy.get('#mdimport-log').should('contain', 'Finished');
  });

  it('made an article for a page', () => {
    articlesTitled('Articles: Featured').should('have.length', 1);
  });

  it('shows the page on the site under its menu item, converted, with its image', () => {
    cy.visit('/index.php/help/articles/articles');
    cy.shouldHaveRendered();
    cy.get('h1, h2').contains('Articles: Introduction').should('exist');
    cy.get('main, #content, body').find('h2').contains('Description').should('exist');

    // The screenshot, copied into the images folder, and actually there.
    cy.get('img[src*="images/jdm-help/articles/articles/01-articles-list.png"]')
      .should('have.length', 1)
      .and(($img) => {
        expect($img[0].naturalWidth, 'the image loaded').to.be.greaterThan(0);
      });

    // A link to another help page goes to that page's menu item, not online.
    cy.get('a[href$="/help/common-elements/toolbars"]').should('exist');
    cy.get('a[href*="jdocmanual?article=help/common-elements"]').should('not.exist');

    // And the source line names where it came from.
    cy.get('.mdimport-attribution a[href="https://jdocmanual.org/en/jdocmanual?article=help/articles/articles"]').should('exist');
    cy.get('.mdimport-attribution a[href="https://guide.joomla.org/contributors/jedl-license"]').should('exist');
  });

  it('nests a subsection under its section in the menu', () => {
    cy.visit('/index.php/help/menu-items/articles/single-article');
    cy.shouldHaveRendered();
    cy.get('h1, h2').contains('Single Article').should('exist');
  });

  it('imports again without making copies', () => {
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_mdimport');
    cy.get('#mdimport-count-pages').invoke('text').then((before) => {
      runImport();
      cy.visit('/administrator/index.php?option=com_mdimport');
      cy.get('#mdimport-count-pages').should('have.text', before);
    });

    articlesTitled('Articles: Featured').should('have.length', 1);
  });
});
