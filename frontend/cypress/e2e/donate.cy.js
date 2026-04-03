// cypress/e2e/donate.cy.js

describe('DonatePage Component', () => {
  beforeEach(() => {
    // Intercepter toutes les requêtes API pour éviter les appels réels
    cy.intercept('POST', '**/api/donate').as('donateRequest');
    cy.visit('/donate');
  });

  describe('Affichage de la page', () => {
    it('affiche le titre et le texte descriptif', () => {
      cy.contains('💖 Soutenir le projet open-hub').should('be.visible');
      cy.contains('open-hub est un projet open source maintenu avec passion').should('be.visible');
      cy.contains('Vos dons permettent de couvrir les coûts').should('be.visible');
    });

    it('affiche le formulaire de don', () => {
      cy.contains('Montant du don (€) :').should('be.visible');
      cy.get('input[type="number"]').should('be.visible');
      cy.get('button').contains('Faire un don 💸').should('be.visible');
    });

    it('affiche un montant par défaut de 5€', () => {
      cy.get('input[type="number"]').should('have.value', '5');
    });
  });

  describe('Modification du montant', () => {
    it('permet de modifier le montant du don', () => {
      cy.get('input[type="number"]').clear().type('10');
      cy.get('input[type="number"]').should('have.value', '10');
    });

    it('accepte uniquement des nombres positifs', () => {
      cy.get('input[type="number"]').should('have.attr', 'min', '1');
      cy.get('input[type="number"]').should('have.attr', 'step', '1');
    });

    it('permet de saisir différents montants', () => {
      const amounts = ['15', '25', '50', '100'];
      
      amounts.forEach(amount => {
        cy.get('input[type="number"]').clear().type(amount);
        cy.get('input[type="number"]').should('have.value', amount);
      });
    });
  });

  describe('Processus de don réussi', () => {

    it('affiche "Redirection..." pendant le traitement', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 200,
        body: { url: 'https://checkout.stripe.com/pay/test' },
        delay: 500
      }).as('slowDonation');

      cy.get('button').contains('Faire un don 💸').click();
      
      // Vérifier le message de chargement
      cy.contains('Redirection...').should('be.visible');
      cy.get('button').should('be.disabled');
      
      // Attendre la fin de la requête
      cy.wait('@slowDonation');
    });

    it('envoie le montant correct dans la requête', () => {
      cy.intercept('POST', '**/api/donate', (req) => {
        expect(req.body.amount).to.equal('25');
        req.reply({
          statusCode: 200,
          body: { url: 'https://stripe.com' }
        });
      }).as('donate');

      cy.get('input[type="number"]').clear().type('25');
      cy.get('button').contains('Faire un don 💸').click();

      cy.wait('@donate');
    });
  });

  describe('Gestion des erreurs', () => {
    it('affiche un message d\'erreur si la requête échoue', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500,
        body: { error: 'Server error', message: 'Erreur serveur' }
      }).as('donationError');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@donationError');
      
      cy.contains('Erreur lors de la création de la session de paiement').should('be.visible');
    });

    it('affiche une erreur réseau', () => {
      cy.intercept('POST', '**/api/donate', {
        forceNetworkError: true
      }).as('networkError');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@networkError');
      
      // Le message d'erreur réseau peut varier selon l'implémentation
      cy.contains(/Erreur|Failed to fetch|Network error/i).should('be.visible');
    });

    it('réactive le bouton après une erreur', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('error');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@error');
      
      cy.get('button').should('not.be.disabled');
      cy.get('button').contains('Faire un don 💸').should('be.visible');
    });

    it('efface les erreurs précédentes lors d\'une nouvelle tentative', () => {
      // Première tentative échoue
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('firstAttempt');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@firstAttempt');
      cy.contains(/Erreur|error/i).should('be.visible');

      // Deuxième tentative réussit
      cy.intercept('POST', '**/api/donate', (req) => {
        req.reply({
          statusCode: 200,
          body: { url: 'about:blank' }
        });
      }).as('secondAttempt');

      // Vérifier que l'erreur disparaît au nouveau clic
      cy.get('button').contains('Faire un don 💸').click();
      
      // L'erreur ne devrait plus être visible
      cy.contains(/Erreur|error/i).should('not.exist');
      cy.wait('@secondAttempt');
    });
  });

  describe('Validation du formulaire', () => {
    it('désactive le bouton pendant la redirection', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 200,
        body: { url: 'https://stripe.com' },
        delay: 1000
      }).as('donate');

      cy.get('button').contains('Faire un don 💸').click();
      cy.get('button').should('be.disabled');
      
      cy.wait('@donate');
    });

    it('envoie plusieurs requêtes avec différents montants', () => {
      const donations = ['5', '10', '20'];
      
      donations.forEach((amount, index) => {
        cy.intercept('POST', '**/api/donate', (req) => {
          expect(req.body.amount).to.equal(amount);
          req.reply({
            statusCode: 500,
            body: { error: 'Test' }
          });
        }).as(`donate${index}`);

        cy.get('input[type="number"]').clear().type(amount);
        cy.get('button').contains('Faire un don 💸').click();
        cy.wait(`@donate${index}`);
        
        // Attendre que le bouton soit réactivé pour le prochain test
        cy.get('button').should('not.be.disabled');
      });
    });
  });

  describe('Style et apparence', () => {
    it('applique les styles correctement', () => {
      cy.get('button').contains('Faire un don 💸')
        .should('have.css', 'background-color')
        .and('match', /rgb\(99,\s*91,\s*255\)|#635bff/i);
      
      cy.get('button').contains('Faire un don 💸')
        .should('have.css', 'color')
        .and('match', /rgb\(255,\s*255,\s*255\)|#ffffff/i);
    });

    it('affiche les erreurs en rouge', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('error');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@error');
      
      // Vérifier que l'erreur est en rouge (plus flexible)
      cy.get('[class*="error"], [class*="errorMessage"], p')
        .filter(':contains("Erreur")')
        .should('have.css', 'color')
        .and('match', /rgb\(255,\s*0,\s*0\)|#ff0000/i);
    });
  });

  describe('Headers de la requête', () => {
    it('envoie les bons headers', () => {
      cy.intercept('POST', '**/api/donate', (req) => {
        expect(req.headers['content-type']).to.include('application/json');
        req.reply({
          statusCode: 200,
          body: { url: 'about:blank' }
        });
      }).as('donate');

      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@donate');
    });
  });

  describe('Cas limites', () => {
    it('gère un montant à 0 (devrait être bloqué par min=1)', () => {
      cy.get('input[type="number"]').clear().type('0');
      cy.get('input[type="number"]').should('have.value', '0');
      
      // Le bouton devrait être cliquable mais l'API devrait recevoir 0
      cy.intercept('POST', '**/api/donate', (req) => {
        expect(req.body.amount).to.equal('0');
        req.reply({
          statusCode: 200,
          body: { url: 'https://stripe.com' }
        });
      }).as('zeroDonation');
      
      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@zeroDonation');
    });

    it('gère un montant très élevé', () => {
      const highAmount = '999999';
      cy.get('input[type="number"]').clear().type(highAmount);
      
      cy.intercept('POST', '**/api/donate', (req) => {
        expect(req.body.amount).to.equal(highAmount);
        req.reply({
          statusCode: 200,
          body: { url: 'https://stripe.com' }
        });
      }).as('highDonation');
      
      cy.get('button').contains('Faire un don 💸').click();
      cy.wait('@highDonation');
    });
  });
});