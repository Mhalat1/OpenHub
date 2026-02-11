// src/JEST/Home.test.jsx
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Home from '../pages/Home';
import '@testing-library/jest-dom';

// Mock de localStorage
const mockLocalStorage = {
  store: {},
  setItem: jest.fn((key, value) => {
    mockLocalStorage.store[key] = value;
    console.log(`💾 localStorage.setItem(${key}, ${value})`);
  }),
  getItem: jest.fn((key) => {
    const value = mockLocalStorage.store[key] || null;
    console.log(`📝 localStorage.getItem(${key}) => ${value}`);
    return value;
  }),
  removeItem: jest.fn((key) => {
    delete mockLocalStorage.store[key];
  }),
  clear: jest.fn(() => {
    mockLocalStorage.store = {};
  }),
};

// Mock de fetch
global.fetch = jest.fn();

describe('Home Component', () => {
  beforeEach(() => {
    // Reset des mocks
    mockLocalStorage.store = {};
    global.fetch.mockClear();
    
    // Définit window.localStorage
    Object.defineProperty(window, 'localStorage', {
      value: mockLocalStorage,
      writable: true
    });
    
    // SIMULE SEULEMENT QU'UN UTILISATEUR EST CONNECTÉ
    // Pas de token JWT fictif, juste un indicateur
    window.localStorage.store = {
      'user_email': 'user@user'  // Seulement l'email
    };
    
    console.log('🔧 Mock initialisé - Email utilisateur: user@user');
  });

  test('1. Affiche les informations utilisateur', async () => {
    console.log('🧪 Test 1: Affichage des données utilisateur');
    
    // Mock SIMPLE des réponses API
    global.fetch.mockImplementation((url) => {
      console.log(`🌐 API appelée: ${url}`);
      
      // Pour /api/getConnectedUser
      if (url.includes('/api/getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: async () => ({
            id: 1,
            firstName: 'John',
            lastName: 'Doe',
            email: 'user@user',
            availabilityStart: '2024-01-01',
            availabilityEnd: '2024-12-31'
          })
        });
      }
      
      // Pour /api/user/skills
      if (url.includes('/api/user/skills')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'JavaScript' },
            { id: 2, name: 'React' }
          ]
        });
      }
      
      // Pour /api/skills (skills disponibles)
      if (url.includes('/api/skills')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'JavaScript' },
            { id: 2, name: 'React' },
            { id: 3, name: 'Node.js' }
          ]
        });
      }
      
      // Pour /api/allprojects
      if (url.includes('/api/allprojects')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'Project Alpha' },
            { id: 2, name: 'Project Beta' }
          ]
        });
      }
      
      // Pour /api/user/projects
      if (url.includes('/api/user/projects')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'Project Alpha' }
          ]
        });
      }
      
      // Par défaut
      return Promise.resolve({
        ok: true,
        json: async () => ({})
      });
    });

    // Rendu du composant
    render(
      <MemoryRouter>
        <Home />
      </MemoryRouter>
    );

    console.log('✅ Composant Home rendu');

    // Attend que le chargement soit terminé
    await waitFor(() => {
      expect(screen.queryByText(/Loading user data.../i)).not.toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifie les données
    expect(screen.getByText('John Doe')).toBeInTheDocument();
    expect(screen.getByText('user@user')).toBeInTheDocument();
    
    console.log('✅ Données utilisateur affichées');
  });

  test('2. Affiche les compétences', async () => {
    console.log('🧪 Test 2: Affichage des compétences');
    
    global.fetch.mockImplementation((url) => {
      if (url.includes('/api/getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: async () => ({
            firstName: 'Alice',
            lastName: 'Smith',
            email: 'alice@example.com'
          })
        });
      }
      
      if (url.includes('/api/user/skills')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'Python' },
            { id: 2, name: 'Django' },
            { id: 3, name: 'PostgreSQL' }
          ]
        });
      }
      
      return Promise.resolve({
        ok: true,
        json: async () => ([])
      });
    });

    render(
      <MemoryRouter>
        <Home />
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Python')).toBeInTheDocument();
      expect(screen.getByText('Django')).toBeInTheDocument();
      expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
    }, { timeout: 3000 });
    
    console.log('✅ Compétences affichées');
  });

  test('3. Affiche les projets', async () => {
    console.log('🧪 Test 3: Affichage des projets');
    
    global.fetch.mockImplementation((url) => {
      if (url.includes('/api/getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: async () => ({
            firstName: 'Bob',
            lastName: 'Johnson',
            email: 'bob@example.com'
          })
        });
      }
      
      if (url.includes('/api/user/projects')) {
        return Promise.resolve({
          ok: true,
          json: async () => [
            { id: 1, name: 'E-commerce Platform' },
            { id: 2, name: 'Mobile App' }
          ]
        });
      }
      
      return Promise.resolve({
        ok: true,
        json: async () => ([])
      });
    });

    render(
      <MemoryRouter>
        <Home />
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('E-commerce Platform')).toBeInTheDocument();
      expect(screen.getByText('Mobile App')).toBeInTheDocument();
    }, { timeout: 3000 });
    
    console.log('✅ Projets affichés');
  });

  test('4. Test d\'erreur de chargement', async () => {
    console.log('🧪 Test 4: Gestion des erreurs');
    
    // Mock une erreur API
    global.fetch.mockImplementation(() => {
      return Promise.resolve({
        ok: false,
        json: async () => ({ error: 'API Error' })
      });
    });

    render(
      <MemoryRouter>
        <Home />
      </MemoryRouter>
    );

    // Devrait afficher un message d'erreur
    await waitFor(() => {
      expect(screen.getByText(/Error/i)).toBeInTheDocument();
    }, { timeout: 3000 });
    
    console.log('✅ Erreur gérée correctement');
  });
});