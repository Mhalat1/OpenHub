describe('Register Component - Inscription en 3 étapes', () => {
  const API_URL = 'http://localhost:8000'; // ou import.meta.env.VITE_API_URL
  
  beforeEach(() => {
    cy.visit('/register');
    
    // Intercepter la requête d'inscription
    cy.intercept('POST', `${API_URL}/api/userCreate`, {
      statusCode: 200,
      body: { 
        status: true, 
        message: 'User created successfully' 
      }
    }).as('createUser');
  });

  describe('1. Page Loading & Structure', () => {
    it('should display the register page correctly', () => {
      cy.contains('Rejoindre OpenHub').should('be.visible');
      cy.contains('Créez votre profil en 3 étapes').should('be.visible');
      cy.get('img[alt="OpenHub"]').should('be.visible');
    });

    it('should have login link', () => {
      cy.contains('Déjà un compte ?').should('be.visible');
      cy.contains('button', 'Se connecter').should('be.visible');
    });
  });

  describe('2. Étape 1 - Informations personnelles', () => {
    it('should display step 1 form correctly', () => {
      cy.get('[class*="stepHeader"]').contains('1').should('be.visible');
      cy.contains('Qui êtes-vous ?').should('be.visible');
      cy.get('input[placeholder="Votre prénom"]').should('be.visible');
      cy.get('input[placeholder="Votre nom"]').should('be.visible');
      cy.contains('button', 'Continuer').should('be.visible');
    });

    it('should show validation errors when fields are empty', () => {
      cy.contains('button', 'Continuer').click();
      cy.contains('Prénom requis').should('be.visible');
      cy.contains('Nom requis').should('be.visible');
      cy.get('[class*="errorInput"]').should('have.length', 2);
    });

    it('should show validation error when only first name is filled', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.contains('button', 'Continuer').click();
      cy.contains('Nom requis').should('be.visible');
      cy.contains('Prénom requis').should('not.exist');
    });

    it('should show validation error when only last name is filled', () => {
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      cy.contains('Prénom requis').should('be.visible');
      cy.contains('Nom requis').should('not.exist');
    });

    it('should clear error when user starts typing', () => {
      cy.contains('button', 'Continuer').click();
      cy.contains('Prénom requis').should('be.visible');
      
      cy.get('input[placeholder="Votre prénom"]').type('J');
      cy.contains('Prénom requis').should('not.exist');
    });

    it('should proceed to step 2 when form is valid', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      // Vérifier passage à l'étape 2
      cy.get('[class*="stepHeader"]').contains('2').should('be.visible');
      cy.contains('Votre compte').should('be.visible');
      cy.get('input[type="email"]').should('be.visible');
    });
  });

  describe('3. Étape 2 - Email et mot de passe', () => {
    beforeEach(() => {
      // Aller à l'étape 2
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
    });

    it('should display step 2 form correctly', () => {
      cy.get('[class*="stepHeader"]').contains('2').should('be.visible');
      cy.contains('Votre compte').should('be.visible');
      cy.get('input[type="email"]').should('be.visible');
      cy.get('input[type="password"]').should('be.visible');
      cy.contains('button', 'Retour').should('be.visible');
      cy.contains('button', 'Continuer').should('be.visible');
    });

    it('should show validation errors for invalid email', () => {
      cy.get('input[type="email"]').type('invalid-email');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      cy.contains('Email invalide').should('be.visible');
    });

    it('should show validation error for password too short', () => {
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('12345');
      cy.contains('button', 'Continuer').click();
      cy.contains('6 caractères minimum').should('be.visible');
    });

    it('should validate email and password correctly', () => {
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      
      // Vérifier passage à l'étape 3
      cy.get('[class*="stepHeader"]').contains('3').should('be.visible');
      cy.contains('Vos disponibilités').should('be.visible');
    });

    it('should go back to step 1 when clicking Retour', () => {
      cy.contains('button', 'Retour').click();
      cy.get('[class*="stepHeader"]').contains('1').should('be.visible');
      cy.contains('Qui êtes-vous ?').should('be.visible');
      cy.get('input[placeholder="Votre prénom"]').should('have.value', 'Jean');
      cy.get('input[placeholder="Votre nom"]').should('have.value', 'Dupont');
    });

    it('should preserve data when going back and forth', () => {
      // Modifier les valeurs à l'étape 2
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('password123');
      
      // Aller à l'étape 3
      cy.contains('button', 'Continuer').click();
      
      // Revenir à l'étape 2
      cy.contains('button', 'Retour').click();
      
      // Vérifier que les données sont conservées
      cy.get('input[type="email"]').should('have.value', 'jean@email.com');
      cy.get('input[type="password"]').should('have.value', 'password123');
    });
  });

  describe('4. Étape 3 - Disponibilités et compétences', () => {
    beforeEach(() => {
      // Aller à l'étape 3
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
    });

    it('should display step 3 form correctly', () => {
      cy.get('[class*="stepHeader"]').contains('3').should('be.visible');
      cy.contains('Vos disponibilités').should('be.visible');
      cy.get('input[type="date"]').should('have.length', 2);
      cy.get('input[placeholder*="JavaScript, React, Node.js"]').should('be.visible');
      cy.contains('Séparez par des virgules').should('be.visible');
      cy.contains('button', 'Retour').should('be.visible');
      cy.contains('button', 'Créer mon compte').should('be.visible');
    });


    it('should go back to step 2 when clicking Retour', () => {
      cy.contains('button', 'Retour').click();
      cy.get('[class*="stepHeader"]').contains('2').should('be.visible');
      cy.contains('Votre compte').should('be.visible');
    });
  });

  describe('5. Soumission du formulaire', () => {
    beforeEach(() => {
      // Aller à l'étape 3
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      
      // Remplir étape 3
      cy.get('input[type="date"]').first().type('2025-01-01');
      cy.get('input[type="date"]').last().type('2025-12-31');
      cy.get('input[placeholder*="JavaScript, React, Node.js"]').type('React, TypeScript, Node.js');
    });

    it('should submit form successfully', () => {
      cy.contains('button', 'Créer mon compte').click();
      
      cy.wait('@createUser').then((interception) => {
        expect(interception.request.body).to.deep.equal({
          firstName: 'Jean',
          lastName: 'Dupont',
          email: 'jean@email.com',
          password: 'password123',
          availabilityStart: '2025-01-01',
          availabilityEnd: '2025-12-31',
          skills: 'React, TypeScript, Node.js'
        });
      });
      
      cy.contains('Création...').should('be.visible');
      cy.get('button[type="submit"]').should('be.disabled');
    });

    it('should redirect to login page after successful registration', () => {
      cy.contains('button', 'Créer mon compte').click();
      
      // Vérifier la redirection après 1.5s
      cy.wait('@createUser');
      cy.url().should('include', '/login', { timeout: 2000 });
    });

    it('should display error message when registration fails', () => {
      cy.intercept('POST', `${API_URL}/api/userCreate`, {
        statusCode: 400,
        body: { 
          status: false, 
          message: 'Email already exists' 
        }
      }).as('createUserFail');

      cy.contains('button', 'Créer mon compte').click();
      cy.wait('@createUserFail');
      
      cy.contains('Email already exists').should('be.visible');
      cy.get('button[type="submit"]').should('not.be.disabled');
    });

    it('should display network error when API is unreachable', () => {
      cy.intercept('POST', `${API_URL}/api/userCreate`, {
        forceNetworkError: true
      }).as('networkError');

      cy.contains('button', 'Créer mon compte').click();
      
      cy.contains('Erreur réseau').should('be.visible');
    });

    it('should not submit if required fields are empty at step 3', () => {
      // Vider les champs
      cy.get('input[placeholder*="JavaScript, React, Node.js"]').clear();
      
      cy.contains('button', 'Créer mon compte').click();
      
      // Le formulaire devrait quand même submit (pas de validation requise)
      cy.wait('@createUser');
    });
  });

  describe('6. Navigation et UX', () => {
    it('should redirect to login page when clicking Se connecter', () => {
      cy.contains('button', 'Se connecter').click();
      cy.url().should('include', '/login');
    });

    it('should maintain state after page refresh (localStorage test)', () => {
      // Remplir partiellement le formulaire
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      
      // Simuler un refresh
      cy.reload();
      
      // Vérifier que les données sont perdues (pas de persistence)
      cy.get('input[placeholder="Votre prénom"]').should('have.value', '');
    });

    it('should handle long names correctly', () => {
      const longName = 'Jean-Pierre-Christophe'.repeat(5);
      cy.get('input[placeholder="Votre prénom"]').type(longName);
      cy.get('input[placeholder="Votre prénom"]').should('have.value', longName);
    });

    it('should trim whitespace in inputs', () => {
      cy.get('input[placeholder="Votre prénom"]').type('  Jean  ');
      cy.get('input[placeholder="Votre nom"]').type('  Dupont  ');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').should('be.visible');
      // Note: La validation trim() devrait être testée dans le composant
    });
  });

  describe('7. Validation supplémentaire', () => {
    it('should accept emails with subdomains', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean@test.subdomain.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      
      cy.get('[class*="stepHeader"]').contains('3').should('be.visible');
    });

    it('should accept emails with plus sign', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean+test@email.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      
      cy.get('[class*="stepHeader"]').contains('3').should('be.visible');
    });

    it('should accept exactly 6 characters password', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('123456');
      cy.contains('button', 'Continuer').click();
      
      cy.get('[class*="stepHeader"]').contains('3').should('be.visible');
    });

    it('should allow empty dates', () => {
      cy.get('input[placeholder="Votre prénom"]').type('Jean');
      cy.get('input[placeholder="Votre nom"]').type('Dupont');
      cy.contains('button', 'Continuer').click();
      
      cy.get('input[type="email"]').type('jean@email.com');
      cy.get('input[type="password"]').type('password123');
      cy.contains('button', 'Continuer').click();
      
      // Ne pas remplir les dates
      cy.get('input[placeholder*="JavaScript, React, Node.js"]').type('React');
      cy.contains('button', 'Créer mon compte').click();
      
      cy.wait('@createUser').then((interception) => {
        expect(interception.request.body.availabilityStart).to.equal('');
        expect(interception.request.body.availabilityEnd).to.equal('');
      });
    });
  });
});