import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';
import { BrowserRouter } from 'react-router-dom';

// Import dynamique pour gérer les exports par défaut et nommés
const ProfilModule = require('../pages/Profil');
const Profil = ProfilModule.default || ProfilModule.Profil || ProfilModule;

// Helper pour render avec Router - NÉCESSAIRE car Profil utilise react-router
const renderWithRouter = (component) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

// Mock des fetch API
global.fetch = jest.fn();

// Mock pour localStorage
const localStorageMock = {
  getItem: jest.fn(() => 'mock-token'),
  setItem: jest.fn(),
  clear: jest.fn(),
  removeItem: jest.fn(),
};
Object.defineProperty(window, 'localStorage', { value: localStorageMock });

// Mock pour window.confirm
window.confirm = jest.fn(() => true);

describe('Profil Component', () => {
  beforeEach(() => {
    fetch.mockClear();
    localStorageMock.getItem.mockClear();
    window.confirm.mockClear();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  const mockUsers = [
    { id: 1, firstName: 'Alice', lastName: 'Smith', email: 'alice@example.com' },
    { id: 2, firstName: 'Bob', lastName: 'Johnson', email: 'bob@example.com' },
    { id: 3, firstName: 'Charlie', lastName: 'Brown', email: 'charlie@example.com' },
  ];

  const mockConnectedUser = { id: 4, firstName: 'John', lastName: 'Doe', email: 'john@example.com' };

  const mockFriends = [
    { id: 5, firstName: 'David', lastName: 'Wilson', email: 'david@example.com' }
  ];

  const mockSentInvitations = [
    { id: 1, recipient_id: 1, firstName: 'Emma', lastName: 'Watson', email: 'emma@example.com', status: 'pending' }
  ];

  const mockReceivedInvitations = [
    { id: 2, sender_id: 6, firstName: 'Frank', lastName: 'Miller', email: 'frank@example.com', status: 'pending' }
  ];

  // Test 1: Render initial state
  test('1. Renders initial state correctly', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockFriends)
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSentInvitations)
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockReceivedInvitations)
        });
      }
      return Promise.reject(new Error('Not found'));
    });

    renderWithRouter(<Profil />);

    // Vérifier que le titre est présent
    await waitFor(() => {
      expect(screen.getByText('Réseau Social')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifier que le champ de recherche est présent
    expect(screen.getByPlaceholderText('Filtrer les utilisateurs...')).toBeInTheDocument();

    // Vérifier que l'onglet "Utilisateurs Publics" est actif par défaut
    const publicTab = screen.getByRole('button', { name: /🌍 Utilisateurs Publics/i });
    expect(publicTab).toHaveClass('active');

    // Vérifier que les utilisateurs sont affichés
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
      expect(screen.getByText('Bob Johnson')).toBeInTheDocument();
      expect(screen.getByText('Charlie Brown')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifier que les onglets ont les bons compteurs
    expect(screen.getByRole('button', { name: /👥 Amis \(1\)/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /📤 Invitations Envoyées \(1\)/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i })).toBeInTheDocument();
  });

  // Test 2: Search functionality
  test('2. Filters users based on search input', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Attendre que les utilisateurs soient chargés
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Saisir un terme de recherche
    const searchInput = screen.getByPlaceholderText('Filtrer les utilisateurs...');
    await user.clear(searchInput);
    await user.type(searchInput, 'Alice');

    // Vérifier que seul Alice Smith est visible
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
      expect(screen.queryByText('Bob Johnson')).not.toBeInTheDocument();
      expect(screen.queryByText('Charlie Brown')).not.toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 3: Switch to friends tab
  test('3. Switches to friends tab and displays friends', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockFriends)
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Attendre que le composant soit chargé
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur l'onglet Amis
    const friendsTab = screen.getByRole('button', { name: /👥 Amis \(1\)/i });
    await user.click(friendsTab);

    // Vérifier que l'onglet Amis est actif
    expect(friendsTab).toHaveClass('active');

    // Vérifier que les amis sont affichés
    await waitFor(() => {
      expect(screen.getByText('David Wilson')).toBeInTheDocument();
      expect(screen.getByText('david@example.com')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifier que le titre de section est correct
    expect(screen.getByText('👥 Mes Amis')).toBeInTheDocument();
  });

  // Test 4: Switch to sent invitations tab
  test('4. Switches to sent invitations tab', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSentInvitations)
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur l'onglet Invitations Envoyées
    const sentTab = screen.getByRole('button', { name: /📤 Invitations Envoyées \(1\)/i });
    await user.click(sentTab);

    // Vérifier que l'onglet est actif
    expect(sentTab).toHaveClass('active');

    // Vérifier que les invitations sont affichées
    await waitFor(() => {
      expect(screen.getByText('Emma Watson')).toBeInTheDocument();
      expect(screen.getByText('emma@example.com')).toBeInTheDocument();
    }, { timeout: 3000 });

    expect(screen.getByText('📤 Invitations Envoyées')).toBeInTheDocument();
  });

  // Test 5: Switch to received invitations tab
  test('5. Switches to received invitations tab', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockReceivedInvitations)
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur l'onglet Invitations Reçues
    const receivedTab = screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i });
    await user.click(receivedTab);

    // Vérifier que l'onglet est actif
    expect(receivedTab).toHaveClass('active');

    // Vérifier que les invitations sont affichées
    await waitFor(() => {
      expect(screen.getByText('Frank Miller')).toBeInTheDocument();
      expect(screen.getByText('frank@example.com')).toBeInTheDocument();
    }, { timeout: 3000 });

    expect(screen.getByText('📥 Invitations Reçues')).toBeInTheDocument();
  });

  // Test 6: Shows empty states
  test('6. Shows empty states correctly', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([mockConnectedUser]) // Seul l'utilisateur connecté
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Aucun utilisateur trouvé')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifier les onglets vides
    const friendsTab = screen.getByRole('button', { name: /👥 Amis \(0\)/i });
    await user.click(friendsTab);
    await waitFor(() => {
      expect(screen.getByText('Aucun ami pour le moment')).toBeInTheDocument();
    }, { timeout: 3000 });

    const sentTab = screen.getByRole('button', { name: /📤 Invitations Envoyées \(0\)/i });
    await user.click(sentTab);
    await waitFor(() => {
      expect(screen.getByText('Aucune invitation envoyée')).toBeInTheDocument();
    }, { timeout: 3000 });

    const receivedTab = screen.getByRole('button', { name: /📥 Invitations Reçues \(0\)/i });
    await user.click(receivedTab);
    await waitFor(() => {
      expect(screen.getByText('Aucune invitation reçue')).toBeInTheDocument();
    }, { timeout: 3000 });
  });

  // Test 7: Opens user modal
  test('7. Opens user modal when clicking on user card', async () => {
    fetch.mockImplementation((url) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Trouver et cliquer sur le bouton +
    const addButtons = screen.getAllByText('+');
    await user.click(addButtons[0]);

    // Attendre que le modal s'ouvre
    await waitFor(() => {
      expect(screen.getByText('➕ Ajouter comme ami')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Vérifier que le modal contient les bonnes informations
    expect(screen.getByText(/📧 Email:/i)).toBeInTheDocument();
    const emailElements = screen.getAllByText('alice@example.com');
    expect(emailElements.length).toBeGreaterThan(0);
  });

  // Test 8: Deletes a friend
  test('8. Deletes a friend successfully', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends') && !url.includes('delete')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockFriends)
        });
      }
      if (url.includes('delete/friends') && options?.method === 'DELETE') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Ami supprimé avec succès' })
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Aller à l'onglet "Amis"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /👥 Amis \(1\)/i })).toBeInTheDocument();
    }, { timeout: 3000 });

    const friendsTab = screen.getByRole('button', { name: /👥 Amis \(1\)/i });
    await user.click(friendsTab);

    await waitFor(() => {
      expect(screen.getByText('David Wilson')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur le bouton Supprimer
    const deleteButton = screen.getByText('❌ Supprimer');
    await user.click(deleteButton);

    // Vérifier que fetch a été appelé pour supprimer
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('delete/friends/5'),
        expect.objectContaining({
          method: 'DELETE',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 9: Accepts a received invitation
  test('9. Accepts a received invitation', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received') && !url.includes('accept')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockReceivedInvitations)
        });
      }
      if (url.includes('invitations/accept') && options?.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Invitation acceptée' })
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Aller à l'onglet "Invitations Reçues"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i })).toBeInTheDocument();
    }, { timeout: 3000 });

    const receivedTab = screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i });
    await user.click(receivedTab);

    await waitFor(() => {
      expect(screen.getByText('Frank Miller')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur le bouton Accepter
    const acceptButton = screen.getByText('Accepter');
    await user.click(acceptButton);

    // Vérifier que fetch a été appelé pour accepter
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('invitations/accept/2'),
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 10: Rejects a received invitation
  test('10. Rejects a received invitation', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received') && !url.includes('delete')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockReceivedInvitations)
        });
      }
      if (url.includes('delete-received') && options?.method === 'DELETE') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Invitation refusée' })
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Aller à l'onglet "Invitations Reçues"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i })).toBeInTheDocument();
    }, { timeout: 3000 });

    const receivedTab = screen.getByRole('button', { name: /📥 Invitations Reçues \(1\)/i });
    await user.click(receivedTab);

    await waitFor(() => {
      expect(screen.getByText('Frank Miller')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur le bouton Refuser
    const rejectButton = screen.getByText('Refuser');
    await user.click(rejectButton);

    // Vérifier que fetch a été appelé pour refuser
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('delete-received/2'),
        expect.objectContaining({
          method: 'DELETE',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 11: Cancels a sent invitation
  test('11. Cancels a sent invitation', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/sent') && !url.includes('delete')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSentInvitations)
        });
      }
      if (url.includes('delete-sent') && options?.method === 'DELETE') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Invitation annulée' })
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    // Aller à l'onglet "Invitations Envoyées"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /📤 Invitations Envoyées \(1\)/i })).toBeInTheDocument();
    }, { timeout: 3000 });

    const sentTab = screen.getByRole('button', { name: /📤 Invitations Envoyées \(1\)/i });
    await user.click(sentTab);

    await waitFor(() => {
      expect(screen.getByText('Emma Watson')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur le bouton Annuler
    const cancelButton = screen.getByText('Annuler');
    await user.click(cancelButton);

    // Vérifier que fetch a été appelé pour annuler
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('delete-sent/1'),
        expect.objectContaining({
          method: 'DELETE',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 12: Sends invitation from modal
  test('12. Sends invitation from modal', async () => {
    fetch.mockImplementation((url, options) => {
      if (url.includes('getConnectedUser')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockConnectedUser)
        });
      }
      if (url.includes('getAllUsers')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUsers)
        });
      }
      if (url.includes('friends')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('send/invitation') && options?.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, message: 'Invitation envoyée' })
        });
      }
      if (url.includes('invitations/sent')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      if (url.includes('invitations/received')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([])
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      });
    });

    renderWithRouter(<Profil />);
    const user = userEvent.setup();

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Ouvrir le modal
    const addButtons = screen.getAllByText('+');
    await user.click(addButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('➕ Ajouter comme ami')).toBeInTheDocument();
    }, { timeout: 3000 });

    // Cliquer sur le bouton d'ajout d'ami dans le modal
    const addFriendButton = screen.getByText('➕ Ajouter comme ami');
    await user.click(addFriendButton);

    // Vérifier que fetch a été appelé pour envoyer l'invitation
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('send/invitation'),
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          }),
          body: JSON.stringify({ friend_id: 1 })
        })
      );
    }, { timeout: 3000 });
  });

  // Test 13: Displays loading state
  test('13. Displays loading state initially', () => {
    fetch.mockImplementation(() => new Promise(() => {})); // Jamais résolue

    renderWithRouter(<Profil />);

    // Vérifier que l'état de chargement est affiché
    expect(screen.getByText('Chargement des données...')).toBeInTheDocument();
  });
});