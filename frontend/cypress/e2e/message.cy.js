describe('Messages Component', () => {
  const mockUser = {
    id: 1,
    firstName: 'John',
    lastName: 'Doe',
    email: 'john@example.com'
  };

  const mockFriends = [
    { id: 2, firstName: 'Jane', lastName: 'Smith', email: 'jane@example.com' },
    { id: 3, firstName: 'Bob', lastName: 'Johnson', email: 'bob@example.com' }
  ];

  const mockConversations = [
    {
      id: 1,
      title: 'Project Discussion',
      description: 'Talk about the new project',
      createdById: 1
    },
    {
      id: 2,
      title: 'Team Meeting',
      description: 'Weekly sync',
      createdById: 2
    }
  ];

  const mockMessages = {
    data: [
      {
        id: 1,
        content: 'Hello everyone!',
        conversationId: 1,
        authorId: 1,
        authorName: 'John Doe',
        createdAt: '2024-01-15T10:00:00Z'
      },
      {
        id: 2,
        content: 'Hi John!',
        conversationId: 1,
        authorId: 2,
        authorName: 'Jane Smith',
        createdAt: '2024-01-15T10:05:00Z'
      },
      {
        id: 3,
        content: 'See you tomorrow',
        conversationId: 2,
        authorId: 3,
        authorName: 'Bob Johnson',
        createdAt: '2024-01-15T11:00:00Z'
      }
    ]
  };

  beforeEach(() => {
    localStorage.setItem('token', 'fake-jwt-token');

    cy.intercept('GET', '**/api/getConnectedUser', {
      statusCode: 200,
      body: mockUser
    }).as('getUser');

    cy.intercept('GET', '**/api/user/friends', {
      statusCode: 200,
      body: mockFriends
    }).as('getFriends');

    cy.intercept('GET', '**/api/get/conversations', {
      statusCode: 200,
      body: mockConversations
    }).as('getConversations');

    cy.intercept('GET', '**/api/get/messages', {
      statusCode: 200,
      body: mockMessages
    }).as('getMessages');

    cy.visit('/messages');
    cy.wait(['@getUser', '@getFriends', '@getConversations', '@getMessages']);
  });

  describe('Affichage initial', () => {
    it('affiche le titre de la page', () => {
      cy.contains('Messages').should('be.visible');
    });

    it('affiche la liste des amis', () => {
      cy.contains('Friends (2)').should('be.visible');
      cy.contains('Jane Smith').should('be.visible');
      cy.contains('Bob Johnson').should('be.visible');
      cy.contains('jane@example.com').should('be.visible');
    });

    it('affiche les initiales des amis', () => {
      cy.contains('JS').should('be.visible');
      cy.contains('BJ').should('be.visible');
    });

    it('affiche la liste des conversations', () => {
      cy.contains('Conversations (2)').should('be.visible');
      cy.contains('Project Discussion').should('be.visible');
      cy.contains('Team Meeting').should('be.visible');
    });

    it('affiche le formulaire de création de conversation', () => {
      cy.contains('New Conversation').should('be.visible');
      cy.get('input[placeholder*="Title"]').should('be.visible');
      cy.get('textarea[placeholder*="Description"]').should('be.visible');
    });
  });

  describe('Gestion des conversations', () => {
    it('ouvre et ferme une conversation au clic', () => {
      cy.contains('Project Discussion').click();
      cy.contains('Hello everyone!').should('be.visible');
      cy.contains('Hi John!').should('be.visible');

      cy.contains('Project Discussion').click();
      cy.contains('Hello everyone!').should('not.exist');
    });

    it('affiche les messages de la bonne conversation', () => {
      cy.contains('Project Discussion').click();
      cy.contains('Hello everyone!').should('be.visible');
      cy.contains('Hi John!').should('be.visible');
      cy.contains('See you tomorrow').should('not.exist');

      cy.contains('Team Meeting').click();
      cy.contains('See you tomorrow').should('be.visible');
      cy.contains('Hello everyone!').should('not.exist');
    });


    it('affiche les dates des messages', () => {
      cy.contains('Project Discussion').click();
      cy.contains(/1\/15\/2024|15\/01\/2024/).should('be.visible');
    });

    it('affiche un message si aucun message dans la conversation', () => {
      cy.intercept('GET', '**/api/get/messages', {
        statusCode: 200,
        body: { data: [] }
      }).as('noMessages');

      cy.visit('/messages');
      cy.wait('@noMessages');

      cy.contains('Project Discussion').click();
      cy.contains('No messages yet').should('be.visible');
    });
  });

  describe('Envoi de messages', () => {
    it('envoie un message avec succès', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 200,
        body: { success: true, message: 'Message created' }
      }).as('sendMessage');

      cy.contains('Project Discussion').click();
      
      cy.get('textarea[placeholder*="Type your message"]')
        .type('This is a test message');
      
      cy.contains('button', 'Send').click();

      cy.wait('@sendMessage').its('request.body').should('deep.equal', {
        content: 'This is a test message',
        conversation_id: 1
      });

      cy.contains('Message sent!').should('be.visible');
    });

    it('valide que le textarea a l\'attribut required', () => {
      cy.contains('Project Discussion').click();
      cy.get('textarea[placeholder*="Type your message"]')
        .should('have.attr', 'required');
    });

    it('limite la longueur du message à 250 caractères via maxLength', () => {
      cy.contains('Project Discussion').click();
      
      cy.get('textarea[placeholder*="Type your message"]')
        .should('have.attr', 'maxLength', '250');
    });

    it('efface le champ après envoi réussi', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 200,
        body: { success: true }
      }).as('sendMessage');

      cy.contains('Project Discussion').click();
      
      cy.get('textarea[placeholder*="Type your message"]')
        .type('Test message');
      
      cy.contains('button', 'Send').click();
      cy.wait('@sendMessage');

      cy.get('textarea[placeholder*="Type your message"]')
        .should('have.value', '');
    });
  });

  describe('Création de conversation', () => {
    it('crée une conversation avec succès', () => {
      cy.intercept('POST', '**/api/create/conversation', {
        statusCode: 200,
        body: { success: true }
      }).as('createConv');

      cy.get('input[placeholder*="Title"]').type('New Project');
      cy.get('textarea[placeholder*="Description"]').type('Discussion about new features');
      
      cy.contains('label', 'Jane Smith').find('input[type="checkbox"]').check();
      
      cy.contains('button', 'Create').click();

      cy.wait('@createConv').its('request.body').should('deep.include', {
        title: 'New Project',
        description: 'Discussion about new features'
      });

      cy.contains('Conversation created successfully!').should('be.visible');
    });

    it('le bouton est désactivé si aucun ami n\'est sélectionné', () => {
      cy.get('input[placeholder*="Title"]').type('New Project');
      cy.contains('button', 'Create').should('be.disabled');
    });

    it('valide la longueur du titre', () => {
      cy.contains('label', 'Jane Smith').find('input[type="checkbox"]').check();
      
      cy.get('input[placeholder*="Title"]')
        .should('have.attr', 'minLength', '2')
        .and('have.attr', 'maxLength', '255');
    });

    it('désactive le bouton si aucun ami n\'est sélectionné', () => {
      cy.contains('button', 'Create').should('be.disabled');
    });

    it('permet de sélectionner plusieurs amis', () => {
      cy.intercept('POST', '**/api/create/conversation', {
        statusCode: 200,
        body: { success: true }
      }).as('createConv');

      cy.get('input[placeholder*="Title"]').type('Group Chat');
      
      cy.contains('label', 'Jane Smith').find('input[type="checkbox"]').check();
      cy.contains('label', 'Bob Johnson').find('input[type="checkbox"]').check();
      
      cy.contains('button', 'Create').click();

      cy.wait('@createConv').its('request.body.conv_users')
        .should('have.length', 2)
        .and('include', 2)
        .and('include', 3);
    });

    it('efface le formulaire après création réussie', () => {
      cy.intercept('POST', '**/api/create/conversation', {
        statusCode: 200,
        body: { success: true }
      }).as('createConv');

      cy.get('input[placeholder*="Title"]').type('Test Conv');
      cy.get('textarea[placeholder*="Description"]').type('Test desc');
      cy.contains('label', 'Jane Smith').find('input[type="checkbox"]').check();
      
      cy.contains('button', 'Create').click();
      cy.wait('@createConv');

      cy.get('input[placeholder*="Title"]').should('have.value', '');
      cy.get('textarea[placeholder*="Description"]').should('have.value', '');
      cy.contains('label', 'Jane Smith')
        .find('input[type="checkbox"]')
        .should('not.be.checked');
    });
  });

  describe('Suppression', () => {
    it('supprime une conversation après confirmation', () => {
      cy.intercept('DELETE', '**/api/delete/conversation/1', {
        statusCode: 200,
        body: { success: true, message: 'Conversation deleted' }
      }).as('deleteConv');

      cy.window().then((win) => {
        cy.stub(win, 'confirm').returns(true);
      });

      cy.get('button').contains('🗑').first().click();

      cy.wait('@deleteConv');
      cy.contains('Conversation deleted').should('be.visible');
    });

    it('ne supprime pas si l\'utilisateur annule', () => {
      cy.window().then((win) => {
        cy.stub(win, 'confirm').returns(false);
      });

      cy.get('button').contains('🗑').first().click();
      
      cy.wait(500);
      cy.contains('Conversation deleted').should('not.exist');
    });

    it('supprime un message après confirmation', () => {
      cy.intercept('DELETE', '**/api/delete/message/1', {
        statusCode: 200,
        body: { success: true, message: 'Message deleted' }
      }).as('deleteMsg');

      cy.window().then((win) => {
        cy.stub(win, 'confirm').returns(true);
      });

      cy.contains('Project Discussion').click();
      
      cy.contains('Hello everyone!')
        .parent()
        .parent()
        .find('button')
        .contains('🗑')
        .click();

      cy.wait('@deleteMsg');
      cy.contains('Message deleted').should('be.visible');
    });

  });

  describe('Gestion des erreurs', () => {
    it('affiche un message si non authentifié', () => {
      localStorage.removeItem('token');
      cy.visit('/messages');
      
      cy.contains('Please log in to view messages').should('be.visible');
    });

    it('affiche une erreur si l\'envoi de message échoue', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('sendError');

      cy.contains('Project Discussion').click();
      cy.get('textarea[placeholder*="Type your message"]').type('Test');
      cy.contains('button', 'Send').click();

      cy.wait('@sendError');
      cy.contains('Server error').should('be.visible');
    });

    it('affiche une erreur si la création de conversation échoue', () => {
      cy.intercept('POST', '**/api/create/conversation', {
        statusCode: 400,
        body: { error: 'Invalid data' }
      }).as('createError');

      cy.get('input[placeholder*="Title"]').type('Test');
      cy.contains('label', 'Jane Smith').find('input[type="checkbox"]').check();
      cy.contains('button', 'Create').click();

      cy.wait('@createError');
      cy.contains('Invalid data').should('be.visible');
    });
  });

  describe('Notifications', () => {
    it('affiche les notifications de succès', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 200,
        body: { success: true }
      }).as('send');

      cy.contains('Project Discussion').click();
      cy.get('textarea[placeholder*="Type your message"]').type('Test');
      cy.contains('button', 'Send').click();

      cy.wait('@send');
      cy.contains('Message sent!').should('be.visible');
    });

    it('affiche les notifications d\'erreur', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('error');

      cy.contains('Project Discussion').click();
      cy.get('textarea[placeholder*="Type your message"]').type('Test');
      cy.contains('button', 'Send').click();

      cy.wait('@error');
      cy.contains('Server error').should('be.visible');
    });

    it('fait disparaître les notifications après 5 secondes', () => {
      cy.intercept('POST', '**/api/create/message', {
        statusCode: 500,
        body: { error: 'Test error' }
      }).as('error');

      cy.contains('Project Discussion').click();
      cy.get('textarea[placeholder*="Type your message"]').type('Test');
      cy.contains('button', 'Send').click();
      
      cy.wait('@error');
      cy.contains('Test error').should('be.visible');
      cy.wait(5100);
      cy.contains('Test error').should('not.exist');
    });
  });

  describe('État de chargement', () => {
    it('affiche un indicateur de chargement', () => {
      cy.intercept('GET', '**/api/getConnectedUser', {
        statusCode: 200,
        body: mockUser,
        delay: 1000
      }).as('slowLoad');

      cy.visit('/messages');
      cy.contains('Loading...').should('be.visible');
    });
  });

  describe('Cas sans données', () => {
    it('affiche un message si aucun ami', () => {
      cy.intercept('GET', '**/api/user/friends', {
        statusCode: 200,
        body: []
      }).as('noFriends');

      cy.visit('/messages');
      cy.wait('@noFriends');

      cy.contains('Friends (0)').should('be.visible');
      cy.contains('No friends available').should('be.visible');
    });

    it('affiche un message si aucune conversation', () => {
      cy.intercept('GET', '**/api/get/conversations', {
        statusCode: 200,
        body: []
      }).as('noConv');

      cy.visit('/messages');
      cy.wait('@noConv');

      cy.contains('Conversations (0)').should('be.visible');
    });
  });
});