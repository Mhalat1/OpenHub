describe('Projects & Skills Management', () => {
  const API_URL = 'http://localhost:8000';
  
  beforeEach(() => {
    cy.intercept('GET', `${API_URL}/api/allprojects`, {
      statusCode: 200,
      body: [
        {
          id: 1,
          name: 'E-commerce Platform',
          description: 'A modern e-commerce solution',
          requiredSkills: 'React, Node.js, MongoDB',
          startDate: '2024-01-01T00:00:00.000Z',
          endDate: '2024-12-31T00:00:00.000Z'
        },
        {
          id: 2,
          name: 'Mobile App',
          description: 'Cross-platform mobile application',
          requiredSkills: 'React Native, Firebase',
          startDate: '2024-02-01T00:00:00.000Z',
          endDate: '2024-08-31T00:00:00.000Z'
        }
      ]
    }).as('getProjects');

    cy.intercept('GET', `${API_URL}/api/skills`, {
      statusCode: 200,
      body: [
        {
          id: 1,
          name: 'Frontend Development',
          description: 'Building modern user interfaces',
          technoUtilisees: 'React, TypeScript, Tailwind',
          duree: '2 years'
        },
        {
          id: 2,
          name: 'Backend Development',
          description: 'Server-side programming',
          technoUtilisees: 'Node.js, Express, PostgreSQL',
          duree: '3 years'
        }
      ]
    }).as('getSkills');

    cy.window().then((win) => {
      win.localStorage.setItem('token', 'fake-jwt-token');
    });

    // Intercepter les requêtes API
    cy.intercept('POST', `${API_URL}/api/skills/create`, {
      statusCode: 200,
      body: { success: true, message: 'Skill created successfully!' }
    }).as('createSkill');

    cy.intercept('POST', `${API_URL}/api/create/new/project`, {
      statusCode: 200,
      body: { message: 'Project created successfully!' }
    }).as('createProject');

    cy.intercept('PUT', `${API_URL}/api/skills/update/*`, {
      statusCode: 200,
      body: { success: true, message: 'Skill updated successfully!' }
    }).as('updateSkill');

    cy.intercept('DELETE', `${API_URL}/api/skills/delete/*`, {
      statusCode: 200,
      body: { success: true, message: 'Skill deleted successfully!' }
    }).as('deleteSkill');

    cy.intercept('PUT', `${API_URL}/api/modify/project/*`, {
      statusCode: 200,
      body: { message: 'Project updated successfully!' }
    }).as('updateProject');

    cy.intercept('DELETE', `${API_URL}/api/delete/project/*`, {
      statusCode: 200,
      body: { message: 'Project deleted successfully!' }
    }).as('deleteProject');
  });

  describe('Page Loading', () => {
    it('should display loading state initially', () => {
      cy.intercept('GET', `${API_URL}/api/allprojects`, (req) => {
        req.reply({ delay: 1000, body: [] });
      }).as('slowProjects');
      
      cy.intercept('GET', `${API_URL}/api/skills`, (req) => {
        req.reply({ delay: 1000, body: [] });
      }).as('slowSkills');
      
      cy.visit('/projects');
      cy.contains('Loading projects and skills...').should('be.visible');
    });

    it('should load and display projects and skills', () => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
      
      cy.contains('Projects & Skills Management').should('be.visible');
      cy.contains('🛠️ Skills Management (2)').should('be.visible');
      cy.contains('📁 Projects Management (2)').should('be.visible');
    });

    it('should display error message when API fails', () => {
      cy.intercept('GET', `${API_URL}/api/allprojects`, {
        statusCode: 500,
        body: { message: 'Server error' }
      }).as('failedProjects');
      
      cy.visit('/projects');
      cy.wait('@failedProjects');
      cy.contains('Error:').should('be.visible');
    });
  });

  describe('Search Functionality', () => {
    beforeEach(() => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
    });

    it('should filter projects by name', () => {
      cy.get('input[placeholder*="Search projects or skills"]').type('E-commerce');
      cy.contains('E-commerce Platform').should('be.visible');
      cy.contains('Mobile App').should('not.exist');
    });

    it('should filter projects by required skills', () => {
      cy.get('input[placeholder*="Search projects or skills"]').type('Firebase');
      cy.contains('Mobile App').should('be.visible');
      cy.contains('E-commerce Platform').should('not.exist');
    });

    it('should filter skills by name', () => {
      cy.get('input[placeholder*="Search projects or skills"]').type('Frontend');
      cy.contains('Frontend Development').should('be.visible');
      cy.contains('Backend Development').should('not.exist');
    });

    it('should filter skills by technologies', () => {
      cy.get('input[placeholder*="Search projects or skills"]').type('Tailwind');
      cy.contains('Frontend Development').should('be.visible');
      cy.contains('Backend Development').should('not.exist');
    });

    it('should show all items when search is cleared', () => {
      cy.get('input[placeholder*="Search projects or skills"]').type('Frontend').clear();
      cy.contains('E-commerce Platform').should('be.visible');
      cy.contains('Mobile App').should('be.visible');
      cy.contains('Frontend Development').should('be.visible');
      cy.contains('Backend Development').should('be.visible');
    });
  });

  describe('Skills Management', () => {
    beforeEach(() => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
    });

    it('should open create skill modal', () => {
      cy.contains('button', '✨ Create New Skill').click();
      cy.contains('h2', 'Create New Skill').should('be.visible');
    });

    it('should create a new skill successfully', () => {
      cy.contains('button', '✨ Create New Skill').click();

      cy.get('input[placeholder="Enter skill name"]').type('DevOps');
      cy.get('textarea[placeholder="Describe the skill..."]').type('CI/CD and deployment');
      cy.get('input[placeholder*="React, JavaScript, Node.js"]').type('Docker, Kubernetes, Jenkins');
      cy.get('input[placeholder*="3 months, 1 year..."]').type('1 year');

      cy.contains('button', '✨ Create Skill').click();
      
      cy.wait('@createSkill');
      cy.contains('✅ Skill created successfully!').should('be.visible');
    });

    it('should show validation error when required fields are empty', () => {
      cy.contains('button', '✨ Create New Skill').click();
      cy.contains('button', '✨ Create Skill').click();
      
      cy.contains('❌ Skill name, description and technologies are required').should('be.visible');
    });

    it('should enforce description character limit', () => {
      cy.contains('button', '✨ Create New Skill').click();

      const longText = 'a'.repeat(60);
      cy.get('textarea[placeholder="Describe the skill..."]').type(longText);
      cy.get('textarea[placeholder="Describe the skill..."]').invoke('val').should('have.length', 50);
      cy.contains('/50').should('be.visible');
    });

    it('should close modal when clicking outside', () => {
      cy.contains('button', '✨ Create New Skill').click();
      cy.contains('h2', 'Create New Skill').should('be.visible');
      
      cy.get('[class*="modalOverlay"]').first().click({ force: true });
      cy.contains('h2', 'Create New Skill').should('not.exist');
    });

    it('should close modal when clicking close button', () => {
      cy.contains('button', '✨ Create New Skill').click();
      cy.contains('h2', 'Create New Skill').should('be.visible');
      
      cy.get('button[class*="closeButton"]').first().click();
      cy.contains('h2', 'Create New Skill').should('not.exist');
    });
  });

  describe('Projects Management', () => {
    beforeEach(() => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
    });

    it('should open create project modal', () => {
      cy.contains('button', '✨ Create New Project').click();
      cy.contains('h2', 'Create New Project').should('be.visible');
    });

    it('should create a new project successfully', () => {
      cy.contains('button', '✨ Create New Project').click();

      cy.get('input[placeholder="Enter project name"]').type('New Project');
      cy.get('textarea[placeholder="Describe the project..."]').type('A test project');
      cy.get('input[placeholder*="React, Node.js, MongoDB..."]').type('Vue, Laravel');
      cy.get('input[type="date"]').first().type('2024-03-01');
      cy.get('input[type="date"]').last().type('2024-09-30');

      cy.contains('button', '✨ Create Project').click();
      
      cy.wait('@createProject');
      cy.contains('✅ Project created successfully!').should('be.visible');
    });

    it('should show validation error for empty project', () => {
      cy.contains('button', '✨ Create New Project').click();
      cy.contains('button', '✨ Create Project').click();
      
      cy.contains('❌ Project name and description are required').should('be.visible');
    });
  });

  describe('Empty States', () => {
    it('should show empty state when no projects exist', () => {
      cy.intercept('GET', `${API_URL}/api/allprojects`, {
        statusCode: 200,
        body: []
      }).as('emptyProjects');

      cy.intercept('GET', `${API_URL}/api/skills`, {
        statusCode: 200,
        body: [
          {
            id: 1,
            name: 'Frontend Development',
            description: 'Building modern user interfaces',
            technoUtilisees: 'React, TypeScript, Tailwind',
            duree: '2 years'
          }
        ]
      }).as('getSkills');

      cy.visit('/projects');
      cy.wait(['@emptyProjects', '@getSkills']);
      
      cy.contains('No projects available').should('be.visible');
      cy.contains('Create your first project to get started').should('be.visible');
    });

    it('should show empty state when no skills exist', () => {
      cy.intercept('GET', `${API_URL}/api/allprojects`, {
        statusCode: 200,
        body: [
          {
            id: 1,
            name: 'E-commerce Platform',
            description: 'A modern e-commerce solution',
            requiredSkills: 'React, Node.js, MongoDB',
            startDate: '2024-01-01T00:00:00.000Z',
            endDate: '2024-12-31T00:00:00.000Z'
          }
        ]
      }).as('getProjects');

      cy.intercept('GET', `${API_URL}/api/skills`, {
        statusCode: 200,
        body: []
      }).as('emptySkills');

      cy.visit('/projects');
      cy.wait(['@getProjects', '@emptySkills']);
      
      cy.contains('No skills available').should('be.visible');
      cy.contains('Create your first skill to get started').should('be.visible');
    });

    it('should show empty state when both projects and skills are empty', () => {
      cy.intercept('GET', `${API_URL}/api/allprojects`, {
        statusCode: 200,
        body: []
      }).as('emptyProjects');

      cy.intercept('GET', `${API_URL}/api/skills`, {
        statusCode: 200,
        body: []
      }).as('emptySkills');

      cy.visit('/projects');
      cy.wait(['@emptyProjects', '@emptySkills']);
      
      cy.contains('No projects available').should('be.visible');
      cy.contains('No skills available').should('be.visible');
    });
  });

  describe('Loading States', () => {
    it('should show loading spinner during create skill action', () => {
      cy.intercept('POST', `${API_URL}/api/skills/create`, (req) => {
        req.reply({ delay: 2000, body: { success: true } });
      }).as('slowCreate');

      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);

      cy.contains('button', '✨ Create New Skill').click();

      cy.get('input[placeholder="Enter skill name"]').type('Test Skill');
      cy.get('textarea[placeholder="Describe the skill..."]').type('Test description');
      cy.get('input[placeholder*="React, JavaScript, Node.js"]').type('Test tech');

      cy.contains('button', '✨ Create Skill').click();
      cy.contains('Creating...').should('be.visible');
    });

    it('should show loading spinner during create project action', () => {
      cy.intercept('POST', `${API_URL}/api/create/new/project`, (req) => {
        req.reply({ delay: 2000, body: { message: 'Project created successfully!' } });
      }).as('slowCreate');

      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);

      cy.contains('button', '✨ Create New Project').click();

      cy.get('input[placeholder="Enter project name"]').type('Test Project');
      cy.get('textarea[placeholder="Describe the project..."]').type('Test description');

      cy.contains('button', '✨ Create Project').click();
      cy.contains('Creating...').should('be.visible');
    });
  });

  describe('Notification System', () => {
    beforeEach(() => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
    });

    it('should display and auto-hide success notifications', () => {
      cy.contains('button', '✨ Create New Skill').click();

      cy.get('input[placeholder="Enter skill name"]').type('Test Skill');
      cy.get('textarea[placeholder="Describe the skill..."]').type('Test description');
      cy.get('input[placeholder*="React, JavaScript, Node.js"]').type('Test tech');
      cy.contains('button', '✨ Create Skill').click();

      cy.contains('✅ Skill created successfully!', { timeout: 10000 }).should('be.visible');
      
      cy.wait(4500);
      cy.contains('✅ Skill created successfully!').should('not.exist');
    });

    it('should display error notifications', () => {
      cy.intercept('POST', `${API_URL}/api/skills/create`, {
        statusCode: 400,
        body: { success: false, message: 'Skill already exists' }
      }).as('failedCreate');

      cy.contains('button', '✨ Create New Skill').click();

      cy.get('input[placeholder="Enter skill name"]').type('Test Skill');
      cy.get('textarea[placeholder="Describe the skill..."]').type('Test description');
      cy.get('input[placeholder*="React, JavaScript, Node.js"]').type('Test tech');
      cy.contains('button', '✨ Create Skill').click();

      cy.contains('❌ Skill already exists', { timeout: 10000 }).should('be.visible');
    });
  });

  describe('UI Responsiveness', () => {
    beforeEach(() => {
      cy.visit('/projects');
      cy.wait(['@getProjects', '@getSkills']);
    });

    it('should display cards in grid layout', () => {
      cy.get('[class*="cardsGrid"]').should('exist');
      cy.get('[class*="card"]').should('have.length.at.least', 4);
    });

    it('should have correct section ordering (Skills first, Projects second)', () => {
      cy.get('[class*="section"]').first().within(() => {
        cy.contains('🛠️ Skills Management').should('be.visible');
      });
      
      cy.get('[class*="section"]').last().within(() => {
        cy.contains('📁 Projects Management').should('be.visible');
      });
    });

    it('should have working buttons in card actions', () => {
      cy.get('[class*="card"]')
        .first()
        .within(() => {
          cy.get('button').contains('✏️').should('be.visible');
          cy.get('button').contains('🗑️').should('be.visible');
        });
    });
  });
});