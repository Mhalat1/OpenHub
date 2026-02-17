describe('LogoutButton Component', () => {
  beforeEach(() => {
    // Configure l'environnement avant chaque test
    cy.visit('/logout'); // Ajuste l'URL selon ta configuration
    localStorage.setItem('token', 'fake-jwt-token');
  });

  it('affiche correctement le contenu de la page', () => {
    cy.get('.logout-title').should('contain', 'See you soon 👋');
  });

  it('affiche les deux boutons', () => {
    cy.get('.logout-btn').should('be.visible').and('contain', 'Logout');
    cy.get('.return-btn').should('be.visible').and('contain', 'Return to Home');
  });

  it('supprime le token et redirige vers /login au clic sur Logout', () => {
    cy.get('.logout-btn').click();
    
    // Vérifie que le token a été supprimé
    cy.window().then((win) => {
      expect(win.localStorage.getItem('token')).to.be.null;
    });

    // Vérifie la redirection après 2 secondes
    cy.url({ timeout: 3000 }).should('include', '/login');
  });

  it('redirige vers /home au clic sur Return to Home', () => {
    cy.get('.return-btn').click();
    cy.url().should('include', '/home');
  });

  it('ne supprime pas le token lors du retour à l\'accueil', () => {
    cy.get('.return-btn').click();
    
    cy.window().then((win) => {
      expect(win.localStorage.getItem('token')).to.equal('fake-jwt-token');
    });
  });
});