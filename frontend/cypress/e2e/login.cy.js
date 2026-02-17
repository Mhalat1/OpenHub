describe('Login Flow', () => {
  beforeEach(() => {
    cy.visit('http://localhost:5173/login');
    cy.intercept('POST', 'http://localhost:8000/api/login_check').as('loginRequest');
  });

  it('should display login form correctly', () => {
    cy.contains('h2', 'Connexion').should('be.visible');
    cy.contains('p', 'Accédez à votre espace personnel').should('be.visible');
    
    cy.get('input[type="email"]')
      .should('be.visible')
      .and('have.attr', 'placeholder', 'votre@email.com');
    
    cy.get('input[type="password"]')
      .should('be.visible')
      .and('have.attr', 'placeholder', 'Votre mot de passe');
    
    cy.get('button[type="submit"]')
      .should('be.visible')
      .and('contain', 'Se connecter');
  });



  it('should show validation errors for empty fields', () => {
    cy.get('button[type="submit"]').click();
    
    // ✅ CORRIGÉ : Chercher par le texte, pas par la classe CSS
    cy.contains('Veuillez remplir tous les champs')
      .should('be.visible');
  });

  it('should show validation error for invalid email format', () => {
    cy.get('input[type="email"]').type('invalid-email');
    cy.get('input[type="password"]').type('password123');
    cy.get('button[type="submit"]').click();
    
    // ✅ CORRIGÉ : Vérifier le message exact
    cy.contains("Invalid email format")
      .should('be.visible');
  });


  it('should show loading state during submission', () => {
    cy.intercept('POST', 'http://localhost:8000/api/login_check', (req) => {
      req.reply((res) => {
        res.delay(1000);
        res.send({ statusCode: 200, body: { token: 'fake-token' } });
      });
    }).as('slowLogin');

    cy.get('input[type="email"]').type('user@user');
    cy.get('input[type="password"]').type('useruser');
    cy.get('button[type="submit"]').click();
    
    // ✅ CORRIGÉ : Vérifier le texte du bouton pendant le chargement
    cy.get('button[type="submit"]')
      .should('contain', 'Connexion...')
      .and('be.disabled');
  });

  it('should navigate to register page', () => {
    // ✅ CORRIGÉ : Chercher par le texte du bouton
    cy.contains('Créer un compte')
      .click();
    cy.url().should('include', '/register');
  });

  it('should navigate to reset password page', () => {
    cy.contains('Mot de passe oublié ?')
      .click();
    cy.url().should('include', '/reset-password');
  });
});