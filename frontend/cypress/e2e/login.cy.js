describe('OpenHub - Page de Login', () => {
  
  beforeEach(() => {
    // Visite la page de login avant chaque test
    cy.visit('http://localhost:3000') // ou votre URL exacte
  })

  // Test 1 : Chargement de la page
  it('affiche correctement la page de login', () => {
    cy.contains('Bienvenue sur OpenHub').should('be.visible')
    cy.contains('Connexion').should('be.visible')
    cy.get('img[alt="OpenHub Logo"]').should('be.visible')
    
    // Vérifie les features
    cy.contains('Connectez avec des développeurs').should('be.visible')
    cy.contains('Partagez vos projets').should('be.visible')
    cy.contains('Collaborez en temps réel').should('be.visible')
  })

  // Test 2 : Champs du formulaire
  it('affiche tous les champs du formulaire', () => {
    cy.get('input#email').should('be.visible')
    cy.get('input#password').should('be.visible')
    cy.get('button[type="submit"]').should('contain', 'Se connecter')
  })

  // Test 3 : Validation - champs vides
  it('affiche une erreur si les champs sont vides', () => {
    cy.get('button[type="submit"]').click()
    cy.contains('Veuillez remplir tous les champs').should('be.visible')
  })

  // Test 4 : Validation - email invalide
  it('affiche une erreur si l\'email est invalide', () => {
    cy.get('input#email').type('emailinvalide')
    cy.get('input#password').type('motdepasse123')
    cy.get('button[type="submit"]').click()
    cy.contains('Format d\'email invalide').should('be.visible')
  })

  // Test 5 : Saisie dans les champs
  it('permet de saisir email et mot de passe', () => {
    cy.get('input#email')
      .type('test@example.com')
      .should('have.value', 'test@example.com')
    
    cy.get('input#password')
      .type('password123')
      .should('have.value', 'password123')
  })

  // Test 6 : Navigation vers l'inscription
  it('navigue vers la page d\'inscription', () => {
    cy.contains('Créer un compte').click()
    cy.url().should('include', '/register')
  })

  // Test 7 : Navigation vers mot de passe oublié
  it('navigue vers la réinitialisation du mot de passe', () => {
    cy.contains('Mot de passe oublié ?').click()
    cy.url().should('include', '/reset-password')
  })

  // Test 8 : Tentative de connexion avec credentials incorrects
  it('affiche une erreur avec des identifiants incorrects', () => {
    // Intercepte la requête API
    cy.intercept('POST', '**/api/login_check', {
      statusCode: 401,
      body: {
        message: 'Identifiants incorrects'
      }
    }).as('loginFailed')

    cy.get('input#email').type('wrong@email.com')
    cy.get('input#password').type('wrongpassword')
    cy.get('button[type="submit"]').click()

    cy.wait('@loginFailed')
    cy.contains('Identifiants incorrects').should('be.visible')
  })

  // Test 9 : Connexion réussie
  it('se connecte avec succès et redirige vers /home', () => {
    // Intercepte la requête API avec succès
    cy.intercept('POST', '**/api/login_check', {
      statusCode: 200,
      body: {
        token: 'fake-jwt-token-for-testing'
      }
    }).as('loginSuccess')

    cy.get('input#email').type('valid@email.com')
    cy.get('input#password').type('validpassword')
    cy.get('button[type="submit"]').click()

    cy.wait('@loginSuccess')
    
    // Vérifie la redirection
    cy.url().should('include', '/home')
    
    // Vérifie le localStorage
    cy.window().then((window) => {
      expect(window.localStorage.getItem('token')).to.equal('fake-jwt-token-for-testing')
      expect(window.localStorage.getItem('user_email')).to.equal('valid@email.com')
    })
  })

  // Test 10 : État de chargement
  it('affiche l\'indicateur de chargement pendant la connexion', () => {
    // Intercepte avec un délai
    cy.intercept('POST', '**/api/login_check', (req) => {
      req.reply({
        delay: 1000,
        statusCode: 200,
        body: { token: 'test-token' }
      })
    })

    cy.get('input#email').type('test@email.com')
    cy.get('input#password').type('password')
    cy.get('button[type="submit"]').click()

    // Vérifie le texte de chargement
    cy.contains('Connexion...').should('be.visible')
    
    // Vérifie que le bouton est désactivé
    cy.get('button[type="submit"]').should('be.disabled')
  })

  // Test 11 : Désactivation des champs pendant le chargement
  it('désactive les champs pendant le chargement', () => {
    cy.intercept('POST', '**/api/login_check', (req) => {
      req.reply({
        delay: 1000,
        statusCode: 200,
        body: { token: 'test-token' }
      })
    })

    cy.get('input#email').type('test@email.com')
    cy.get('input#password').type('password')
    cy.get('button[type="submit"]').click()

    // Vérifie que les inputs sont désactivés
    cy.get('input#email').should('be.disabled')
    cy.get('input#password').should('be.disabled')
  })
})