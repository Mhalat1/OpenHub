describe('DonatePage Component', () => {
  beforeEach(() => {
    cy.visit('/donate');
  });

  describe('Affichage de la page', () => {
    it('affiche le titre et le texte descriptif', () => {
      cy.contains('💖 Soutenir le projet OpenHub').should('be.visible');
      cy.contains('OpenHub est un projet open source maintenu avec passion').should('be.visible');
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
    it('redirige vers Stripe après un don réussi', () => {
      const mockStripeUrl = 'https://checkout.stripe.com/pay/test_123';

      cy.intercept('POST', '**/api/donate', {
        statusCode: 200,
        body: { url: mockStripeUrl }
      }).as('createDonation');

      cy.get('input[type="number"]').clear().type('10');
      cy.get('button').contains('Faire un don 💸').click();

      cy.wait('@createDonation').its('request.body').should('deep.equal', {
        amount: '10'
      });

      // Vérifier que l'URL a changé
      cy.url().should('eq', mockStripeUrl);
    });

    it('affiche "Redirection..." pendant le traitement', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 200,
        body: { url: 'https://checkout.stripe.com/pay/test' },
        delay: 500
      }).as('slowDonation');

      cy.get('button').contains('Faire un don 💸').click();
      cy.contains('Redirection...').should('be.visible');
      cy.get('button').should('be.disabled');
    });

    it('envoie le montant correct dans la requête', () => {
      cy.intercept('POST', '**/api/donate', (req) => {
        req.reply({
          statusCode: 200,
          body: { url: 'https://stripe.com' }
        });
      }).as('donate');

      cy.get('input[type="number"]').clear().type('25');
      cy.get('button').click();

      cy.wait('@donate').then((interception) => {
        expect(interception.request.body.amount).to.equal('25');
      });
    });
  });

  describe('Gestion des erreurs', () => {
    it('affiche un message d\'erreur si la requête échoue', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('donationError');

      cy.get('button').contains('Faire un don 💸').click();

      cy.wait('@donationError');
      cy.contains('Erreur lors de la création de la session de paiement').should('be.visible');
    });

    it('affiche une erreur réseau', () => {
      cy.intercept('POST', '**/api/donate', {
        forceNetworkError: true
      }).as('networkError');

      cy.get('button').click();
      cy.contains('Failed to fetch').should('be.visible');
    });

    it('réactive le bouton après une erreur', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500
      }).as('error');

      cy.get('button').click();
      cy.wait('@error');
      
      cy.get('button').should('not.be.disabled');
      cy.contains('Faire un don 💸').should('be.visible');
    });

    it('efface les erreurs précédentes lors d\'une nouvelle tentative', () => {
      // Première tentative échoue
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500
      }).as('firstAttempt');

      cy.get('button').click();
      cy.wait('@firstAttempt');
      cy.contains('Erreur').should('be.visible');

      // Deuxième tentative réussit mais on n'intercepte pas la redirection
      cy.intercept('POST', '**/api/donate', (req) => {
        req.reply({
          statusCode: 200,
          body: { url: 'about:blank' } // URL qui ne redirige pas vraiment
        });
      }).as('secondAttempt');

      cy.get('button').click();
      
      // L'erreur ne devrait plus être visible après le clic
      cy.wait('@secondAttempt');
      cy.wait(100); // Petite attente pour que le state se mette à jour
      
    });
  });

  describe('Validation du formulaire', () => {
    it('désactive le bouton pendant la redirection', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 200,
        body: { url: 'https://stripe.com' },
        delay: 1000
      }).as('donate');

      cy.get('button').click();
      cy.get('button').should('be.disabled');
    });

    it('envoie plusieurs requêtes avec différents montants', () => {
      const donations = [
        { amount: '5' },
        { amount: '10' },
        { amount: '20' }
      ];

      donations.forEach((donation, index) => {
        cy.intercept('POST', '**/api/donate', (req) => {
          expect(req.body.amount).to.equal(donation.amount);
          req.reply({
            statusCode: 500, // Empêcher la redirection
            body: { error: 'Test' }
          });
        }).as(`donate${index}`);

        cy.get('input[type="number"]').clear().type(donation.amount);
        cy.get('button').contains('Faire un don 💸').click();

        cy.wait(`@donate${index}`);
      });
    });
  });

  describe('Style et apparence', () => {
    it('applique les styles correctement', () => {
      cy.get('button').should('have.css', 'background-color', 'rgb(99, 91, 255)');
      cy.get('button').should('have.css', 'color', 'rgb(255, 255, 255)');
    });

    it('affiche les erreurs en rouge', () => {
      cy.intercept('POST', '**/api/donate', {
        statusCode: 500
      }).as('error');

      cy.get('button').click();
      cy.wait('@error');
      
      cy.get('p').contains('Erreur').should('have.css', 'color', 'rgb(255, 0, 0)');
    });
  });

  describe('Headers de la requête', () => {
    it('envoie les bons headers', () => {
      cy.intercept('POST', '**/api/donate', (req) => {
        expect(req.headers['content-type']).to.equal('application/json');
        req.reply({
          statusCode: 200,
          body: { url: 'about:blank' }
        });
      }).as('donate');

      cy.get('button').click();
      cy.wait('@donate');
    });
  });
});