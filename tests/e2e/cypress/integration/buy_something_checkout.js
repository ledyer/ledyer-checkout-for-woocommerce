// ledyer.js created with Cypress
//
// Start writing your Cypress tests below!
// If you're unfamiliar with how Cypress works,
// check out the link below and learn how to write your first test:
// https://on.cypress.io/writing-first-test

describe('Creating Ledyer Order', () => {

    const getIframeDocument = () => {
        return cy
            .get('iframe[id^="ledyer-checkout-se_"]')
            // Cypress yields jQuery element, which has the real
            // DOM element under property "0".
            // From the real DOM iframe element we can get
            // the "document" element, it is stored in "contentDocument" property
            // Cypress "its" command can access deep properties using dot notation
            // https://on.cypress.io/its
            .its('0.contentDocument').should('exist')
    }

    const getIframeBody = () => {
        // get the document
        return getIframeDocument()
            // automatically retries until body is loaded
            .its('body').should('not.be.undefined')
            // wraps "body" DOM element to allow
            // chaining more Cypress commands, like ".find(...)"
            .then(cy.wrap)
    }

    it('log in', () => {
        cy.visit('/');
        //cy.get('#ast-hf-menu-1 > .main-header-menu > .page-item-9 > .menu-link').click();
        cy.visit('/my-account/')
        cy.get('#username').type('admin');
        cy.get('#password').type('password');
        cy.get('.woocommerce-button').click();
        cy.wait(2000);

        //Add simple and variable product to cart
        cy.visit('/shop/');
        //cy.get('.ast-primary-header-bar > .site-primary-header-wrap > .ast-builder-grid-row > .site-header-primary-section-left > .ast-builder-layout-element > .site-branding > .ast-site-title-wrap > .site-title > a').click();
        cy.get('.post-10 > .button').click();
        cy.wait(1500);

        //Work with iframe
        cy.visit('/checkout/');

        cy.wait(1500);

        // getIframeBody().then( $body => {
        //     if( $body.find('button[data-test-id="submit-customerinfo-button"]').length > 0 ) {
        //         getIframeBody().find('input#companyId').type('559311-3714');
        //         getIframeBody().find('input#email').clear();
        //         getIframeBody().find('input#email').type('test@gmail.com');
        //         getIframeBody().find('input#phone').clear();
        //         getIframeBody().find('input#phone').type('567845329');
        //         getIframeBody().find('input#reference1').type('My Test Ref');
        //         getIframeBody().find('button[data-test-id="submit-customerinfo-button"]').should('exist').click();
        //         getIframeBody().find('button[data-test-id="submit-btn-complete-purchase"]').should('exist').click();
        //     } else {
        //         getIframeBody().find('button[data-test-id="submit-btn-complete-purchase"]').should('exist').click();
        //     }
        // } );
        //
        //
        // cy.wait(15000);
        // cy.url({ decode: true }).should('contain', 'order-received');
    });

})
